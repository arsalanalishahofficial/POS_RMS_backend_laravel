<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OrderReceipt;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    // --------------------
    // Fillable fields
    // --------------------
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
        'delivery_charge',   
        'delivery_status',   
        'receipt_number',  
        'customer_id',
        'rider_id',
        'is_cancelled'
    ];

    // --------------------
    // Casts
    // --------------------
    protected $casts = [
        'is_cancelled' => 'boolean',
        'grand_total' => 'float',
        'discount' => 'float',
        'net_total' => 'float',
        'cash_received' => 'float',
        'change_due' => 'float',
        'delivery_charge' => 'float',   
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    // --------------------
    // Helpers
    // --------------------
    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isDelivery()
    {
        return $this->type === 'delivery';
    }

    public function isDelivered()
    {
        return $this->delivery_status === 'delivered';
    }

    // --------------------
    // Receipt number (2-day cycle)
    // --------------------
    public function getReceiptNumberAttribute()
    {
        $created = $this->created_at ?? now();
        $dayNumber = $created->diffInDays(now()->startOfYear());
        $cycleStart = now()->startOfYear()->addDays(intdiv($dayNumber, 2) * 2)->toDateString();

        $receipt = OrderReceipt::firstOrCreate(
            ['cycle_start' => $cycleStart],
            ['current_number' => 0]
        );

        return $receipt->current_number + 1;
    }
}
