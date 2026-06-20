<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventReminder;
use Google\Service\Calendar\EventReminders;
use Illuminate\Support\Carbon;

class GoogleCalendarService
{
    protected ?Setting $settings = null;

    protected ?\Google\Service\Calendar $service = null;

    public function __construct(?Setting $settings = null)
    {
        $this->settings = $settings ?? Setting::first();
    }

    /**
     * Whether Google Calendar is connected (we have a refresh token).
     */
    public function isConnected(): bool
    {
        if (! $this->settings || ! $this->settings->google_calendar_refresh_token) {
            return false;
        }

        $clientId = $this->settings->google_calendar_client_id ?? config('google_calendar.client_id');
        $clientSecret = $this->settings->google_calendar_client_secret ?? config('google_calendar.client_secret');

        return ! empty($clientId) && ! empty($clientSecret);
    }

    /**
     * Get OAuth2 client and set refresh token from settings.
     */
    public function getClient(): GoogleClient
    {
        $clientId = $this->settings->google_calendar_client_id ?? config('google_calendar.client_id');
        $clientSecret = $this->settings->google_calendar_client_secret ?? config('google_calendar.client_secret');
        $redirectUri = $this->settings->google_calendar_redirect_uri ?? config('google_calendar.redirect_uri') ?? url('/google-calendar/callback');

        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope('https://www.googleapis.com/auth/calendar');
        $client->addScope('https://www.googleapis.com/auth/calendar.events');

        if ($this->settings && $this->settings->google_calendar_refresh_token) {
            $client->setAccessToken([
                'refresh_token' => $this->settings->google_calendar_refresh_token,
                'expires_in'    => 0,
            ]);
            $client->fetchAccessTokenWithRefreshToken($this->settings->google_calendar_refresh_token);
        }

        return $client;
    }

    /**
     * Get Calendar API service instance.
     */
    protected function getService(): Calendar
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $client = $this->getClient();
        $this->service = new Calendar($client);

        return $this->service;
    }

    /**
     * Timezone for event start/end (store timezone or app default).
     */
    protected function getTimezone(): string
    {
        if ($this->settings && ! empty($this->settings->timezone)) {
            return $this->settings->timezone;
        }

        return config('app.timezone', 'UTC');
    }

    /**
     * Build start and end datetime in RFC3339 for the given booking (in store timezone).
     */
    protected function bookingToRfc3339(Booking $booking): array
    {
        $tz = $this->getTimezone();
        $date = $booking->booking_date;
        $startTime = $booking->booking_time;
        $endTime = $booking->booking_end_time ?? $startTime;

        $start = Carbon::parse($date->format('Y-m-d').' '.$startTime, $tz);
        $end = Carbon::parse($date->format('Y-m-d').' '.$endTime, $tz);

        return [
            'start' => $start->format('Y-m-d\TH:i:sP'),
            'end'   => $end->format('Y-m-d\TH:i:sP'),
        ];
    }

    /**
     * Build event summary and description from booking.
     */
    protected function eventSummaryAndDescription(Booking $booking): array
    {
        $booking->loadMissing(['customer', 'product']);
        $summary = 'Booking '.$booking->Ref;
        if ($booking->product) {
            $summary .= ' - '.$booking->product->name;
        }

        $lines = [
            'Customer: '.optional($booking->customer)->name,
            'Email: '.optional($booking->customer)->email,
            'Phone: '.optional($booking->customer)->phone,
        ];
        if ($booking->product) {
            $lines[] = 'Service: '.$booking->product->name;
        }
        if ($booking->price !== null) {
            $lines[] = 'Price: '.$booking->price;
        }
        if (! empty($booking->notes)) {
            $lines[] = 'Notes: '.$booking->notes;
        }
        $description = implode("\n", $lines);

        return [$summary, $description];
    }

    /**
     * Create a Google Calendar event for a confirmed booking.
     * Returns the event id, or null on failure.
     */
    public function createEvent(Booking $booking): ?string
    {
        if (! $this->isConnected()) {
            return null;
        }

        try {
            $service = $this->getService();
            $calendarId = $this->settings->google_calendar_calendar_id
                ?? config('google_calendar.default_calendar_id', 'primary');
            $tz = $this->getTimezone();

            $dates = $this->bookingToRfc3339($booking);
            [$summary, $description] = $this->eventSummaryAndDescription($booking);

            $event = new Event;
            $event->setSummary($summary);
            $event->setDescription($description);

            $start = new EventDateTime;
            $start->setDateTime($dates['start']);
            $start->setTimeZone($tz);
            $event->setStart($start);

            $end = new EventDateTime;
            $end->setDateTime($dates['end']);
            $end->setTimeZone($tz);
            $event->setEnd($end);

            $reminders = new EventReminders;
            $reminders->setUseDefault(false);
            $overrides = [];
            foreach (config('google_calendar.reminders', []) as $rem) {
                $reminder = new EventReminder;
                $reminder->setMethod($rem['method']);
                $reminder->setMinutes($rem['minutes']);
                $overrides[] = $reminder;
            }
            $reminders->setOverrides($overrides);
            $event->setReminders($reminders);

            $created = $service->events->insert($calendarId, $event);

            return $created->getId();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Update an existing Google Calendar event for a booking.
     */
    public function updateEvent(Booking $booking): bool
    {
        if (! $this->isConnected() || empty($booking->google_calendar_event_id)) {
            return false;
        }

        try {
            $service = $this->getService();
            $calendarId = $this->settings->google_calendar_calendar_id
                ?? config('google_calendar.default_calendar_id', 'primary');
            $tz = $this->getTimezone();

            $existing = $service->events->get($calendarId, $booking->google_calendar_event_id);
            $dates = $this->bookingToRfc3339($booking);
            [$summary, $description] = $this->eventSummaryAndDescription($booking);

            $existing->setSummary($summary);
            $existing->setDescription($description);

            $start = new EventDateTime;
            $start->setDateTime($dates['start']);
            $start->setTimeZone($tz);
            $existing->setStart($start);

            $end = new EventDateTime;
            $end->setDateTime($dates['end']);
            $end->setTimeZone($tz);
            $existing->setEnd($end);

            $reminders = new EventReminders;
            $reminders->setUseDefault(false);
            $overrides = [];
            foreach (config('google_calendar.reminders', []) as $rem) {
                $reminder = new EventReminder;
                $reminder->setMethod($rem['method']);
                $reminder->setMinutes($rem['minutes']);
                $overrides[] = $reminder;
            }
            $reminders->setOverrides($overrides);
            $existing->setReminders($reminders);

            $service->events->update($calendarId, $booking->google_calendar_event_id, $existing);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Delete a Google Calendar event by id.
     */
    public function deleteEvent(string $eventId): bool
    {
        if (! $this->isConnected() || empty($eventId)) {
            return false;
        }

        try {
            $service = $this->getService();
            $calendarId = $this->settings->google_calendar_calendar_id
                ?? config('google_calendar.default_calendar_id', 'primary');
            $service->events->delete($calendarId, $eventId);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
