<?php

namespace App\Services;

use App\Models\Event;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

// M3a EVT-01..09, blueprint §7.8: the ONLY writer of the events table.
// Controllers and services call this, never Event::create() directly.
// Origin: every M3a flow runs through the normal buyer UI, so its
// origin is 'normal' (PRD ACC-15 / K-04: activity by a simulation
// account through the normal UI counts as normal activity).
//
// M4 SIM-12/24/29 (rencana §2.4/R4): the developer panel ALSO writes
// through this recorder — it does NOT write its own rows. A caller
// passes origin='developer_simulation' (plus simulation_run_id and
// occurred_at) explicitly; this recorder never invents an origin or a
// run id on its own. The defaults stay 'normal'/null, so every
// existing caller behaves exactly as before.
class ActivityRecorder
{
    private function dedupMinutes(): int
    {
        return max(1, (int) config('ira.view_event_dedup_minutes', 30));
    }

    // EVT-02: one 'view' per (account, product) per dedup window.
    // Returns true when a row was written, false when deduped.
    // The window is measured back from the event's own timestamp, so
    // a backdated view dedups against its own period, not against now().
    public function recordView(User $user, Product $product, ?string $sessionRef = null, string $origin = 'normal', ?string $simulationRunId = null, ?Carbon $occurredAt = null): bool
    {
        $reference = $occurredAt ?? now();
        $since = $reference->copy()->subMinutes($this->dedupMinutes());

        $exists = Event::query()
            ->where('event_type', 'view')
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('occurred_at', '>=', $since)
            ->exists();

        if ($exists) {
            return false;
        }

        $this->write('view', $user, $product, null, null, null, $sessionRef, $origin, $simulationRunId, $occurredAt);

        return true;
    }

    // EVT-03: every successful add-to-cart, never on failure.
    public function recordAddToCart(User $user, Product $product, ?string $sessionRef = null, string $origin = 'normal', ?string $simulationRunId = null, ?Carbon $occurredAt = null): void
    {
        $this->write('add_to_cart', $user, $product, null, null, null, $sessionRef, $origin, $simulationRunId, $occurredAt);
    }

    // EVT-04: one per order item, in the same transaction as the order.
    // Returns false when the booking event already exists (the partial
    // unique index is the backstop; this check keeps the call idempotent).
    public function recordBooking(OrderItem $item, User $user, string $origin = 'normal', ?string $simulationRunId = null, ?Carbon $occurredAt = null): bool
    {
        if ($this->existsForItem($item->id, 'booking')) {
            return false;
        }

        $this->write('booking', $user, $item->product, $item->order_id, $item->id, null, null, $origin, $simulationRunId, $occurredAt);

        return true;
    }

    // EVT-05: one per order item, ONLY when the payment becomes paid.
    public function recordPurchase(OrderItem $item, User $user, string $origin = 'normal', ?string $simulationRunId = null, ?Carbon $occurredAt = null): bool
    {
        if ($this->existsForItem($item->id, 'purchase')) {
            return false;
        }

        $this->write('purchase', $user, $item->product, $item->order_id, $item->id, null, null, $origin, $simulationRunId, $occurredAt);

        return true;
    }

    // EVT-06: one per order item on cancel, reason buyer|expired.
    // Never for a failed payment.
    public function recordCancel(OrderItem $item, User $user, string $reason, string $origin = 'normal', ?string $simulationRunId = null, ?Carbon $occurredAt = null): bool
    {
        if ($this->existsForItem($item->id, 'cancel')) {
            return false;
        }

        $this->write('cancel', $user, $item->product, $item->order_id, $item->id, $reason, null, $origin, $simulationRunId, $occurredAt);

        return true;
    }

    // M4 SIM-12/24: orphan funnel events for the simulator — a view or
    // add-to-cart with no order behind it. No dedup (the funnel counts
    // are planned, not observed); origin + run + timestamp are given
    // explicitly by the caller. No order_item_id, so the per-item
    // idempotency checks above do not apply here.
    public function recordSimulationView(User $user, Product $product, string $origin = 'developer_simulation', ?string $simulationRunId = null, ?Carbon $occurredAt = null, ?string $sessionRef = null): bool
    {
        $this->write('view', $user, $product, null, null, null, $sessionRef, $origin, $simulationRunId, $occurredAt);

        return true;
    }

    public function recordSimulationAddToCart(User $user, Product $product, string $origin = 'developer_simulation', ?string $simulationRunId = null, ?Carbon $occurredAt = null, ?string $sessionRef = null): void
    {
        $this->write('add_to_cart', $user, $product, null, null, null, $sessionRef, $origin, $simulationRunId, $occurredAt);
    }

    private function existsForItem(int $orderItemId, string $type): bool
    {
        return Event::query()
            ->where('order_item_id', $orderItemId)
            ->where('event_type', $type)
            ->exists();
    }

    private function write(
        string $type,
        User $user,
        Product $product,
        ?int $orderId,
        ?int $orderItemId,
        ?string $reason,
        ?string $sessionRef,
        string $origin = 'normal',
        ?string $simulationRunId = null,
        ?Carbon $occurredAt = null,
    ): void {
        Event::create([
            'event_type' => $type,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'session_ref' => $sessionRef,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'reason' => $reason,
            'occurred_at' => $occurredAt ?? now(),
            'origin' => $origin,
            'simulation_run_id' => $simulationRunId,
        ]);
    }
}
