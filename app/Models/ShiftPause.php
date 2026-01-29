<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Shift;

class ShiftPause extends Model
{
    use HasFactory;

    protected $fillable = ['shift_id', 'paused_at', 'resumed_at'];

    protected $casts = [
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
