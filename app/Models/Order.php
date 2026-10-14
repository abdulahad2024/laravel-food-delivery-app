<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'restaurant_id',
        'address_id',
        'rider_id',
        'coupon_id',
        'coupon_code',
        'coupon_discount_amount',
        'status',
        'delivery_status',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'discount_amount',
        'total',
        'notes',
        'order_number',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }

    public function settlement()
    {
        return $this->hasOne(Settlement::class);
    }
}
