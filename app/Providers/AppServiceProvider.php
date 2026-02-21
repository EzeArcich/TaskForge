<?php

namespace App\Providers;

use App\Application\Contracts\AiNormalizerInterface;
use App\Application\Contracts\CalendarProviderInterface;
use App\Application\Contracts\KanbanProviderInterface;
use App\Infrastructure\AI\OpenAiNormalizer;
use App\Infrastructure\Calendar\GoogleCalendarProvider;
use App\Infrastructure\Kanban\TrelloProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind ports to adapters (Hexagonal Architecture)
        $this->app->bind(AiNormalizerInterface::class, OpenAiNormalizer::class);
        $this->app->bind(KanbanProviderInterface::class, TrelloProvider::class);
        $this->app->bind(CalendarProviderInterface::class, GoogleCalendarProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
