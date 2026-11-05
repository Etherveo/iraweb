<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M4 slice 1 (buyer profile, Data_Pembeli §2). One buyer may have many
// addresses. The address_type CHECK ('Rumah'/'Kantor'/'Lainnya') and the
// 5-character postal_code CHECK live in migration 000014; this model only
// carries the fillable fields and the owner relation.
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'recipient_name', 'recipient_phone', 'province',
        'city', 'district', 'village', 'postal_code', 'street_address',
        'address_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
