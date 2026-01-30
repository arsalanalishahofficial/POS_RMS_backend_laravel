<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\MenuItem;

class PriceHistory extends Model
{
    use HasFactory;

     protected $fillable = [
        'menu_item_id',
        'user_id',
        'old_price',
        'new_price'
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class); 
    }
}
