<?php

namespace App\Observers;

use App\Jobs\SyncBookingToGoogleCalendar;
use App\Models\Booking;

class BookingObserver
{
    public function created(Booking $booking): void
    {
        if ($booking->status === 'confirmed') {
            SyncBookingToGoogleCalendar::dispatch(
                $booking->id,
                SyncBookingToGoogleCalendar::ACTION_UPSERT
            );
        }
    }

    public function updated(Booking $booking): void
    {
        $relevant = ['status', 'booking_date', 'booking_time', 'booking_end_time', 'customer_id', 'product_id', 'price', 'notes'];
        if (empty(array_intersect($relevant, array_keys($booking->getChanges())))) {
            return;
        }

        SyncBookingToGoogleCalendar::dispatch(
            $booking->id,
            SyncBookingToGoogleCalendar::ACTION_UPSERT
        );
    }

    public function deleted(Booking $booking): void
    {
        if (! empty($booking->google_calendar_event_id)) {
            SyncBookingToGoogleCalendar::dispatch(
                $booking->id,
                SyncBookingToGoogleCalendar::ACTION_DELETE,
                $booking->google_calendar_event_id
            );
        }
    }
}
