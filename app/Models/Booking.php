<?php

namespace App\Models;

use App\Observers\BookingObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::observe(BookingObserver::class);
    }

    protected $dates = ['booking_date', 'deleted_at'];

    protected $fillable = [
        'Ref',
        'customer_id',
        'product_id',
        'price',
        'booking_date',
        'booking_time',
        'booking_end_time',
        'status',
        'notes',
        'google_calendar_event_id',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'product_id' => 'integer',
        'price' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Client::class, 'customer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}












