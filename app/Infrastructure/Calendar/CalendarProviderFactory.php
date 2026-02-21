<?php

namespace App\Infrastructure\Calendar;

use App\Application\Contracts\CalendarProviderInterface;
use InvalidArgumentException;

class CalendarProviderFactory
{
    /** @var array<string, class-string<CalendarProviderInterface>> */
    private array $providers = [
        'google' => GoogleCalendarProvider::class,
    ];

    public function make(string $provider): CalendarProviderInterface
    {
        $class = $this->providers[$provider] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unsupported calendar provider: {$provider}. Supported: " . implode(', ', array_keys($this->providers)));
        }

        return app($class);
    }

    public function supports(string $provider): bool
    {
        return isset($this->providers[$provider]);
    }
}
