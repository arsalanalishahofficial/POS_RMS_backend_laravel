<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItemAdjustment extends Model
{
    use HasFactory;

    protected $table = 'order_item_adjustments';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'menu_item_id',
        'receipt_number',

        'old_quantity',
        'new_quantity',
        'adjusted_quantity',

        'price',
        'amount_impact',

        'action',
        'user_id',

        'reason',
        'ip_address',
    ];

    /**
     * Relationships
     */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isCancelled()
    {
        return $this->action === 'cancelled';
    }

    public function isDecreased()
    {
        return $this->action === 'decreased';
    }

}
