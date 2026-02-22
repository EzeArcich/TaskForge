<?php

namespace App\Infrastructure\Calendar;

use App\Application\Contracts\CalendarProviderInterface;
use App\Models\Integration;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarProvider implements CalendarProviderInterface
{
    private string $baseUrl = 'https://www.googleapis.com/calendar/v3';

    public function createEvents(Plan $plan): array
    {
        $timezone = $plan->settings['timezone'] ?? 'UTC';
        $calendarId = 'primary';
        $eventIds = [];

        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            if (! $task->scheduled_date || ! $task->scheduled_start || ! $task->scheduled_end) {
                continue;
            }

            $startDateTime = $this->toGoogleDateTime($task->scheduled_date->format('Y-m-d'), (string) $task->scheduled_start);
            $endDateTime = $this->toGoogleDateTime($task->scheduled_date->format('Y-m-d'), (string) $task->scheduled_end);

            $event = $this->request('POST', "/calendars/{$calendarId}/events", [
                'summary' => 'Deep Work: ' . $task->title,
                'description' => sprintf(
                    "DailyPro Task\nEstimate: %.1fh\nPlan: %s",
                    $task->estimate_hours,
                    $plan->normalized_json['title'] ?? ''
                ),
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => $timezone,
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 10],
                    ],
                ],
            ]);

            $eventIds[$task->id] = $event['id'];
        }

        Log::info('Google Calendar events created', [
            'plan_id' => $plan->id,
            'events_created' => count($eventIds),
        ]);

        return [
            'calendar_id' => $calendarId,
            'event_ids' => $eventIds,
        ];
    }

    public function updateEvents(Plan $plan): void
    {
        $timezone = $plan->settings['timezone'] ?? 'UTC';
        $calendarId = $plan->google_calendar_id ?? 'primary';

        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            if (! $task->google_event_id || ! $task->scheduled_date) {
                continue;
            }

            $startDateTime = $this->toGoogleDateTime($task->scheduled_date->format('Y-m-d'), (string) $task->scheduled_start);
            $endDateTime = $this->toGoogleDateTime($task->scheduled_date->format('Y-m-d'), (string) $task->scheduled_end);

            $this->request('PUT', "/calendars/{$calendarId}/events/{$task->google_event_id}", [
                'summary' => 'Deep Work: ' . $task->title,
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => $timezone,
                ],
            ]);
        }
    }

    public function deleteEvents(Plan $plan): void
    {
        $calendarId = $plan->google_calendar_id ?? 'primary';

        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            if (! $task->google_event_id) {
                continue;
            }

            try {
                $this->request('DELETE', "/calendars/{$calendarId}/events/{$task->google_event_id}");
            } catch (\Throwable $e) {
                Log::warning('Failed to delete calendar event', [
                    'event_id' => $task->google_event_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getAccessToken(): string
    {
        // Prefer token from integrations table (OAuth callback), fallback to .env.
        $integrationToken = $this->getGoogleIntegration()?->access_token;

        $token = $integrationToken ?: config('dailypro.google.access_token');
        if (! $token) {
            throw new \RuntimeException('Google Calendar access token not configured in integrations table or .env. Complete OAuth flow first.');
        }

        return $token;
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;
        $integration = $this->getGoogleIntegration();

        // Proactive refresh if token is known to be expired.
        if ($integration?->expires_at && now()->greaterThanOrEqualTo($integration->expires_at->subMinute())) {
            Log::info('Google access token expired. Attempting proactive refresh.', [
                'integration_id' => $integration->id,
                'expires_at' => $integration->expires_at?->toDateTimeString(),
            ]);
            $this->refreshAccessToken($integration);
        }

        $response = $this->sendRequest($this->getAccessToken(), $method, $url, $data);

        // If access token expired, refresh once and retry.
        if ($response->status() === 401) {
            Log::warning('Google API returned 401. Attempting token refresh.', [
                'method' => $method,
                'path' => $path,
            ]);
            $refreshedToken = $this->refreshAccessToken($integration);
            if ($refreshedToken) {
                $response = $this->sendRequest($refreshedToken, $method, $url, $data);
            } else {
                Log::warning('Google token refresh unavailable or failed; keeping original 401 response.');
            }
        }

        if ($method === 'DELETE' && $response->successful()) {
            return [];
        }

        $response->throw();

        return $response->json() ?? [];
    }

    private function sendRequest(string $accessToken, string $method, string $url, array $data = [])
    {
        $client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->retry(2, 500);

        return match ($method) {
            'GET' => $client->get($url, $data),
            'POST' => $client->post($url, $data),
            'PUT' => $client->put($url, $data),
            'DELETE' => $client->delete($url),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };
    }

    private function refreshAccessToken(?Integration $integration = null): ?string
    {
        $integration ??= $this->getGoogleIntegration();
        $refreshToken = $integration?->refresh_token;
        if (! $integration || ! $refreshToken) {
            Log::warning('Google token refresh skipped: missing integration or refresh_token.');
            return null;
        }

        $clientId = (string) config('services.google.client_id');
        $clientSecret = (string) config('services.google.client_secret');
        if ($clientId === '' || $clientSecret === '') {
            Log::warning('Google token refresh skipped: missing client_id/client_secret in config.');
            return null;
        }

        $response = Http::asForm()
            ->retry(1, 500)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        if ($response->failed()) {
            Log::warning('Google token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $newAccessToken = (string) $response->json('access_token');
        if ($newAccessToken === '') {
            return null;
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 0);
        $integration->update([
            'access_token' => $newAccessToken,
            'refresh_token' => $response->json('refresh_token') ?: $integration->refresh_token,
            'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : $integration->expires_at,
        ]);

        Log::info('Google access token refreshed successfully.', [
            'integration_id' => $integration->id,
            'expires_in' => $expiresIn,
        ]);

        return $newAccessToken;
    }

    private function getGoogleIntegration(): ?Integration
    {
        $withRefresh = Integration::query()
            ->where('provider', 'google')
            ->whereNotNull('refresh_token')
            ->orderByDesc('updated_at')
            ->first();

        if ($withRefresh) {
            return $withRefresh;
        }

        return Integration::query()
            ->whereIn('provider', ['google', 'google_calendar'])
            ->orderByDesc('updated_at')
            ->first();
    }

    private function toGoogleDateTime(string $date, string $time): string
    {
        $normalizedTime = trim($time);

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $date . ' ' . $normalizedTime)->format('Y-m-d\TH:i:s');
            } catch (\Throwable) {
                // try next format
            }
        }

        throw new \InvalidArgumentException("Invalid task time format: {$time}");
    }
}
