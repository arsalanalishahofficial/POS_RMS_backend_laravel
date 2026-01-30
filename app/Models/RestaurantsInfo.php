<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantsInfo extends Model
{
    use HasFactory;

     protected $table = 'restaurants_info';

    protected $fillable = [
        'name',
        'address',
        'phone_number',
        'promo_tagline_top',
        'promo_tagline_bottom',
    ];
}
