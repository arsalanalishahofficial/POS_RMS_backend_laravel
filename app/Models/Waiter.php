<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Waiter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
    ];

    protected $dates = ['deleted_at'];


    public function transactions()
    {
        return $this->hasMany(WaiterTransaction::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }


    public function netTotal()
    {
        return $this->transactions()
            ->selectRaw("SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END) as net_total")
            ->value('net_total') ?? 0;
    }
}
