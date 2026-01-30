<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashOut extends Model
{
    use HasFactory;

     protected $fillable = [
        'casher_id',
        'shift_id',
        'amount',
        'type',
        'note'
    ];
    public function casher()
    {
        return $this->belongsTo(User::class, 'casher_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
