<?php

namespace App\Models;

use Database\Factories\RestaurantUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class RestaurantUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'status',
        'restaurant_name',
        'slug',
        'restaurant_description',
        'restaurant_address',
        'restaurant_logo',
        'cover_image',
        'restaurant_status',
        'approval_status',
        'delivery_fee',
        'minimum_order_value',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'restaurant_logo_url',
        'cover_image_url'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function getRestaurantLogoUrlAttribute(): ?string
    {
        return $this->restaurant_logo ? Storage::disk('public')->url($this->restaurant_logo) : null;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }
}
