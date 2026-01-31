<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'status',       
        'waiter_id',
        'table_id',
        'floor_id',
        'grand_total',
        'discount',
        'net_total',
        'cash_received',
        'change_due',
        'is_cancelled'
    ];

    protected $casts = [
        'is_cancelled' => 'boolean',
        'grand_total'  => 'float',
        'discount'     => 'float',
        'net_total'    => 'float',
        'cash_received'=> 'float',
        'change_due'   => 'float',
    ];

    // --------------------
    // Relationships
    // --------------------
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function waiter()
    {
        return $this->belongsTo(Waiter::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    // --------------------
    // Helpers (optional but useful)
    // --------------------
    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }
    
}
