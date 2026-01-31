<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderReceipt extends Model
{
     use HasFactory;

    protected $fillable = ['cycle_start', 'current_number'];
}
