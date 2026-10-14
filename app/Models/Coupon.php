<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'minimum_order_value',
        'starts_at',
        'expires_at',
        'max_usage',
        'is_active',
    ];

    protected $casts = [
        'value' => 'float',
        'minimum_order_value' => 'float',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isValidForSubtotal(float $subtotal, ?int $userId = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ((float) $this->minimum_order_value > 0 && $subtotal < (float) $this->minimum_order_value) {
            return false;
        }

        if (! is_null($this->max_usage)) {
            $query = $this->orders();

            if ($userId) {
                $query->where('customer_id', $userId);
            }

            if ($query->count() >= (int) $this->max_usage) {
                return false;
            }
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if (! $this->isValidForSubtotal($subtotal)) {
            return 0;
        }

        if ($this->type === 'percentage') {
            return round(min($subtotal, $subtotal * ((float) $this->value / 100)), 2);
        }

        if ($this->type === 'fixed') {
            return round(min($subtotal, (float) $this->value), 2);
        }

        return 0;
    }
}
