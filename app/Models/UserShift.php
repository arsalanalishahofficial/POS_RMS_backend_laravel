<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shift_id',
        'terminal_id',
        'login_at',
        'logout_at',
    ];

    public function terminal()
    {
        return $this->belongsTo(Terminal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
