<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ShiftPause;

class Shift extends Model
{
    use HasFactory;

     protected $fillable = [
        'shift_start',
        'shift_end',
        'is_paused',
    ];

     protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'is_paused' => 'boolean',
    ];

    public function pauses()
{
    return $this->hasMany(ShiftPause::class);
}
}
