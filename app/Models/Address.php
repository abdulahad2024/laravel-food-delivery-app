<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'address_line_1',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
