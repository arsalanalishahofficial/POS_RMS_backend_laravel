<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'kitchen_id'];

    public function kitchen()
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}
