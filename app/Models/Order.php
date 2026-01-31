<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OrderReceipt;

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
        'grand_total' => 'float',
        'discount' => 'float',
        'net_total' => 'float',
        'cash_received' => 'float',
        'change_due' => 'float',
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

    public function getReceiptNumberAttribute()
    {
        // Determine current 3-day cycle start
        $created = $this->created_at ?? now();
        $dayNumber = $created->diffInDays(now()->startOfYear());
        $cycleStart = now()->startOfYear()->addDays(intdiv($dayNumber, 3) * 3)->toDateString();

        // Lock to prevent race condition
        $receipt = OrderReceipt::firstOrCreate(
            ['cycle_start' => $cycleStart],
            ['current_number' => 0]
        );

        return $receipt->current_number + 1; // temporary, will update after order created
    }


}
