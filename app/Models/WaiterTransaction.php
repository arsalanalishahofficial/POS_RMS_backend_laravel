<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaiterTransaction extends Model
{
     use HasFactory;

    protected $table = 'waiter_transactions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'waiter_id',
        'type',   // 'deposit' or 'return'
        'amount',
        'note',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Relationship: Each transaction belongs to a waiter
     */
    public function waiter()
    {
        return $this->belongsTo(Waiter::class);
    }
}
