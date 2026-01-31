<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'waiter_id',
        'floor_id',
        'status',
        'parent_table_id',
        'is_sub_table'
    ];

    protected $dates = ['deleted_at'];

    public function parentTable()
    {
        return $this->belongsTo(Table::class, 'parent_table_id');
    }

    public function subTables()
    {
        return $this->hasMany(Table::class, 'parent_table_id');
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }


    public function waiter()
    {
        return $this->belongsTo(Waiter::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }


}
