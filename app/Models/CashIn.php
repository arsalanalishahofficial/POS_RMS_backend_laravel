<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'casher_id',
        'shift_id',
        'amount',
        'note'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function casher()
    {
        return $this->belongsTo(User::class, 'casher_id');
    }
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

}
