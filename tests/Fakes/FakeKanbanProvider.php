<?php

namespace Tests\Fakes;

use App\Application\Contracts\KanbanProviderInterface;
use App\Models\Plan;

class FakeKanbanProvider implements KanbanProviderInterface
{
    public array $createdBoards = [];
    public array $movedCards = [];
    public array $updatedDueDates = [];
    public bool $shouldFail = false;

    public function createBoard(Plan $plan): array
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('Fake Trello error');
        }

        $cardIds = [];
        foreach ($plan->tasks as $task) {
            $cardIds[$task->id] = 'fake_card_' . $task->id;
        }

        $result = [
            'board_id' => 'fake_board_' . $plan->id,
            'board_url' => 'https://trello.com/b/fake/' . $plan->id,
            'card_ids' => $cardIds,
        ];

        $this->createdBoards[] = $result;

        return $result;
    }

    public function moveCard(string $cardId, string $listName): void
    {
        $this->movedCards[] = ['card_id' => $cardId, 'list' => $listName];
    }

    public function updateCardDueDates(Plan $plan): void
    {
        $this->updatedDueDates[] = $plan->id;
    }

    public function getTodayCardIds(string $boardId): array
    {
        return [];
    }
}
