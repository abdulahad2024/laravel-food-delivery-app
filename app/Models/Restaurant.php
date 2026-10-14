<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Restaurant extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'profile_image',
        'phone',
        'email',
        'status',
        'approval_status',
        'delivery_fee',
        'minimum_order_value',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'delivery_fee' => 'float',
        'minimum_order_value' => 'float',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'profile_image_url',
    ];

    public function getProfileImageUrlAttribute(): ?string
    {
        return $this->profile_image !== null
            ? Storage::disk('public')->url($this->profile_image)
            : null;
    }

    public function owner()
    {
        return $this->belongsTo(RestaurantUser::class, 'owner_id');
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function foods()
    {
        return $this->hasMany(Food::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
