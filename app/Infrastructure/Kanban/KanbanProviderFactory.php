<?php

namespace App\Infrastructure\Kanban;

use App\Application\Contracts\KanbanProviderInterface;
use InvalidArgumentException;

class KanbanProviderFactory
{
    /** @var array<string, class-string<KanbanProviderInterface>> */
    private array $providers = [
        'trello' => TrelloProvider::class,
    ];

    public function make(string $provider): KanbanProviderInterface
    {
        $class = $this->providers[$provider] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unsupported kanban provider: {$provider}. Supported: " . implode(', ', array_keys($this->providers)));
        }

        return app($class);
    }

    public function supports(string $provider): bool
    {
        return isset($this->providers[$provider]);
    }
}
