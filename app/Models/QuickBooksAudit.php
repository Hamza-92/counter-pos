<?php

// app/Models/QuickBooksAudit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickBooksAudit extends Model
{
    protected $fillable = [
        'user_id', 'sale_id', 'realm_id', 'environment', 'operation', 'level', 'message',
        'http_code', 'request_payload', 'response_body', 'sdk_error',
    ];
}
