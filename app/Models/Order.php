<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'waiter_id', 'table_id', 'floor_id', 'grand_total', 'discount', 'net_total', 'cash_received', 'change_due', 'is_cancelled'
    ];

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
}
