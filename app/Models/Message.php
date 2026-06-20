<?php

// app/Models/Message.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'subject', 'message', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'integer',
    ];
}
