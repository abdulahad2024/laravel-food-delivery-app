<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'status',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'commission_amount' => 'float',
        'net_amount' => 'float',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
