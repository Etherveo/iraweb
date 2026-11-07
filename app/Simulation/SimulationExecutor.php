<?php

namespace App\Simulation;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SimulationRun;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Support\OrderNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

// M4 simulator (SIM-13/27/30, blueprint §9.6/§9.2). Executes one
// hash-verified SimulationPlan inside ONE DB transaction, in the
// blueprint's write order (orders → items → payments →
// transitions/histories → events), in bulk-insert chunks of ±1,000
// rows. Any failure rolls the whole run back; the run is then marked
// 'gagal' with the message OUTSIDE the rolled-back transaction, and
// the audit row is still written (SIM-27).
//
// INVARIANT (SIM-14): no sim order is left in a non-final status —
// every order exits as selesai/dibatalkan/kedaluwarsa inside the same
// transaction (SimulationPaymentService drives each order through its
// full transition chain before execute() returns).
//
// The plan is executed VERBATIM: item prices are the ones resolved
// from the live catalog when the plan was built (preview); SIM-15's
// "harga item memakai harga produk saat eksekusi" means exactly that
// — the plan is built at execution time, not replayed from a stale
// price — and the params hash covers those prices. What the executor
// re-checks at execution time is existence + active status (a product
// deactivated after the preview fails the whole run, SIM-14).
//
// Order numbers keep the EXECUTION date (rencana §3.2, keputusan):
// the number is a unique identifier, not a business date —
// OrderNumber::generate() is used as-is, no date parameter.
final class SimulationExecutor
{
    private const BULK_CHUNK_SIZE = 1000;

    public function __construct(
        private SimulationPaymentService $paymentService,
        private ActivityRecorder $events,
        private SimulationAudit $audit,
    ) {}

    /**
     * @return array{accounts:int, orders:int, order_items:int, payments:int, events:int}
     */
    public function execute(SimulationPlan $plan, SimulationRun $run): array
    {
        if (SimulationPlan::hashParams($plan->params) !== $plan->paramsHash) {
            throw ValidationException::withMessages([
                'plan' => 'Pratinjau simulasi tidak cocok dengan parameter yang diajukan (SIM-26). Buat pratinjau baru.',
            ]);
        }

        if ($plan->action !== $run->action) {
            throw ValidationException::withMessages([
                'plan' => "Pratinjau aksi '{$plan->action}' tidak cocok dengan run aksi '{$run->action}'.",
            ]);
        }

        if ($run->status !== 'running') {
            throw ValidationException::withMessages([
                'run' => 'Run ini sudah berstatus '.$run->status.' dan tidak dapat dijalankan lagi.',
            ]);
        }

        if ($plan->orders === []) {
            throw ValidationException::withMessages([
                'plan' => 'Pratinjau tidak memuat satu pun pesanan.',
            ]);
        }

        try {
            $counts = DB::transaction(fn () => $this->writePlan($plan, $run));
        } catch (Throwable $e) {
            $this->audit->recordRunFailed($run, $e->getMessage());

            throw $e;
        }

        $this->audit->recordRunFinish($run, $counts);

        return $counts;
    }

    /** @return array{accounts:int, orders:int, order_items:int, payments:int, events:int} */
    private function writePlan(SimulationPlan $plan, SimulationRun $run): array
    {
        $productIds = [];
        $userIds = [];

        foreach ($plan->orders as $planned) {
            $userIds[] = (int) $planned['user_id'];

            foreach ($planned['items'] as $item) {
                $productIds[] = (int) $item['product_id'];
            }
        }

        foreach ($plan->orphanEvents as $orphan) {
            $userIds[] = (int) $orphan['user_id'];
            $productIds[] = (int) $orphan['product_id'];
        }

        $products = Product::query()
            ->whereIn('id', array_values(array_unique($productIds)))
            ->get()
            ->keyBy('id');

        foreach ($plan->orders as $planned) {
            foreach ($planned['items'] as $item) {
                $product = $products->get((int) $item['product_id']);

                if ($product === null || ! (bool) $product->is_active) {
                    throw ValidationException::withMessages([
                        'products' => 'Produk id '.$item['product_id'].' tidak ditemukan atau sudah tidak aktif; seluruh permintaan ditolak (SIM-14).',
                    ]);
                }
            }
        }

        $productNames = $products->map(fn (Product $product): string => $product->name)->all();

        $users = User::query()
            ->whereIn('id', array_values(array_unique($userIds)))
            ->get()
            ->keyBy('id');

        foreach (array_unique($userIds) as $userId) {
            if (! $users->has($userId)) {
                throw ValidationException::withMessages([
                    'users' => 'Akun simulasi id '.$userId.' tidak ditemukan (SIM-14).',
                ]);
            }
        }

        // --- Orders (bulk) ---
        $orderRows = [];

        foreach ($plan->orders as $planned) {
            $orderRows[] = [
                'order_number' => OrderNumber::generate(),
                'user_id' => (int) $planned['user_id'],
                'status' => 'menunggu_pembayaran',
                'total_idr' => (int) $planned['total_idr'],
                'payment_deadline_at' => $this->fmt($planned['payment_deadline_at']),
                'paid_at' => null,
                'checkout_key' => (string) Str::uuid(),
                'origin' => 'developer_simulation',
                'simulation_run_id' => $run->id,
                'created_at' => $this->fmt($planned['booking_at']),
                'updated_at' => $this->fmt($planned['booking_at']),
            ];
        }

        foreach (array_chunk($orderRows, self::BULK_CHUNK_SIZE) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $orderIds = Order::query()
            ->whereIn('order_number', array_column($orderRows, 'order_number'))
            ->pluck('id', 'order_number')
            ->all();

        // --- Order items (bulk) ---
        $itemRows = [];

        foreach ($plan->orders as $index => $planned) {
            $orderId = (int) $orderIds[$orderRows[$index]['order_number']];

            foreach ($planned['items'] as $item) {
                $itemRows[] = [
                    'order_id' => $orderId,
                    'product_id' => (int) $item['product_id'],
                    'product_name_snapshot' => $productNames[(int) $item['product_id']],
                    'unit_price_idr' => (int) $item['unit_price_idr'],
                    'quantity' => (int) $item['quantity'],
                    'subtotal_idr' => (int) $item['subtotal_idr'],
                    'origin' => 'developer_simulation',
                    'simulation_run_id' => $run->id,
                    'created_at' => $this->fmt($planned['booking_at']),
                    'updated_at' => $this->fmt($planned['booking_at']),
                ];
            }
        }

        foreach (array_chunk($itemRows, self::BULK_CHUNK_SIZE) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }

        $orders = Order::query()
            ->whereIn('id', array_values($orderIds))
            ->get()
            ->keyBy('id');

        $itemsByOrder = OrderItem::query()
            ->whereIn('order_id', array_values($orderIds))
            ->get()
            ->groupBy('order_id');

        // --- Per order: chain events, then the payment (which writes
        // transitions, histories, and the purchase/cancel events). The
        // §9.6 phase order (parents before children, all inside one
        // transaction) is honored; per-order interleaving is required
        // because purchase/cancel are part of the payment outcome.
        $totalItems = 0;

        foreach ($plan->orders as $index => $planned) {
            $order = $orders->get((int) $orderIds[$orderRows[$index]['order_number']]);
            $user = $users->get((int) $planned['user_id']);
            $viewAt = $this->toCarbon($planned['view_at']);
            $cartAt = $this->toCarbon($planned['add_to_cart_at']);
            $bookingAt = $this->toCarbon($planned['booking_at']);

            $eloquentItems = $itemsByOrder->get($order->id, collect())->keyBy('product_id');

            foreach ($planned['items'] as $item) {
                $product = $products->get((int) $item['product_id']);
                $eloquentItem = $eloquentItems->get((int) $item['product_id']);

                // recordSimulationView/AddToCart: the no-dedup variants.
                // recordView dedups per (user, product) in a 30-min
                // window measured from the event's own stamp — with a
                // 'single' time distribution or a dense period it would
                // silently drop planned views and break the SIM-24
                // invariant (view ≥ cart ≥ booking). The rows produced
                // are identical (chain view/cart carry no order/item
                // refs in the normal flow either), minus dedup.
                $this->events->recordSimulationView($user, $product, 'developer_simulation', $run->id, $viewAt);
                $this->events->recordSimulationAddToCart($user, $product, 'developer_simulation', $run->id, $cartAt);
                $this->events->recordBooking($eloquentItem, $user, 'developer_simulation', $run->id, $bookingAt);

                $totalItems++;
            }

            $this->paymentService->applyForOrder($order, $planned, $run);
        }

        // --- Orphan events (no order behind them) ---
        foreach ($plan->orphanEvents as $orphan) {
            $user = $users->get((int) $orphan['user_id']);
            $product = $products->get((int) $orphan['product_id']);
            $at = $this->toCarbon($orphan['occurred_at']);

            if ($orphan['type'] === 'add_to_cart') {
                $this->events->recordSimulationAddToCart($user, $product, 'developer_simulation', $run->id, $at);
            } else {
                $this->events->recordSimulationView($user, $product, 'developer_simulation', $run->id, $at);
            }
        }

        // Counts are exact by construction: any failure above rolls the
        // whole transaction back, so nothing here is partial.
        return [
            'accounts' => 0,
            'orders' => count($plan->orders),
            'order_items' => $totalItems,
            'payments' => count($plan->orders),
            'events' => 4 * $totalItems + count($plan->orphanEvents),
        ];
    }

    /** Plan instants are CarbonImmutable (UTC); the domain services
     * take mutable Illuminate\Support\Carbon. */
    private function toCarbon(CarbonImmutable $instant): Carbon
    {
        return Carbon::instance($instant);
    }

    /** Bulk-insert datetime: seconds precision, monotonic, so the
     * chain order (view ≤ cart ≤ booking ≤ paid) is preserved and
     * every stamp stays inside the period. */
    private function fmt(CarbonImmutable $instant): string
    {
        return $instant->format('Y-m-d H:i:s');
    }
}
