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
        'floor_id'
    ];

    protected $dates = ['deleted_at'];

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }
    
    public function waiter()
    {
        return $this->belongsTo(Waiter::class);
    }
}
