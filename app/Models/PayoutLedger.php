<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutLedger extends Model
{
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'settlement_id',
        'type',
        'direction',
        'amount',
        'status',
        'reference',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }
}
