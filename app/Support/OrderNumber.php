<?php

namespace App\Support;

use App\Models\Order;

// M3a ORD-03, moved here from CheckoutService (M4 Langkah 4) so the
// simulator can reuse the same numbering without going through a
// checkout. Algorithm unchanged: IRA-YYMMDD-XXXXXX, 6 random chars
// from an unambiguous alphabet (no 0/O, 1/I/L), unique, non-sequential.
class OrderNumber
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $number = 'IRA-'.now()->format('ymd').'-'.$code;
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
