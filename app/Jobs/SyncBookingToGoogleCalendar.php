<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBookingToGoogleCalendar implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    public int $tries = 5;
    public int $timeout = 60;

    public int $bookingId;
    public string $action;
    public ?string $eventId;

    public function __construct(int $bookingId, string $action, ?string $eventId = null)
    {
        $this->bookingId = $bookingId;
        $this->action = $action;
        $this->eventId = $eventId;
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function uniqueId(): string
    {
        return $this->action.':'.$this->bookingId;
    }

    public function handle(GoogleCalendarService $calendar): void
    {
        if (! $calendar->isConnected()) {
            return;
        }

        if ($this->action === self::ACTION_DELETE) {
            if (! empty($this->eventId)) {
                $calendar->deleteEvent($this->eventId);
            }
            return;
        }

        $booking = Booking::with(['customer', 'product'])->find($this->bookingId);
        if (! $booking) {
            return;
        }

        if ($booking->status !== 'confirmed') {
            if (! empty($booking->google_calendar_event_id)) {
                $calendar->deleteEvent($booking->google_calendar_event_id);
                $booking->forceFill(['google_calendar_event_id' => null])->saveQuietly();
            }
            return;
        }

        if (! empty($booking->google_calendar_event_id)) {
            $calendar->updateEvent($booking);
            return;
        }

        $eventId = $calendar->createEvent($booking);
        if ($eventId) {
            $booking->forceFill(['google_calendar_event_id' => $eventId])->saveQuietly();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncBookingToGoogleCalendar failed', [
            'booking_id' => $this->bookingId,
            'action' => $this->action,
            'event_id' => $this->eventId,
            'message' => $e->getMessage(),
        ]);
    }
}
