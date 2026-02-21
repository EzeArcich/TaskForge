<?php

namespace App\Application\Contracts;

use App\Models\Plan;

interface KanbanProviderInterface
{
    /**
     * Create a board with lists and cards for the plan.
     *
     * @return array{board_id: string, board_url: string, card_ids: array<string, string>}
     */
    public function createBoard(Plan $plan): array;

    /**
     * Move a card to a target list.
     */
    public function moveCard(string $cardId, string $listName): void;

    /**
     * Update due dates on existing cards.
     */
    public function updateCardDueDates(Plan $plan): void;

    /**
     * Get cards currently in the "Hoy" (Today) list.
     *
     * @return array<string>
     */
    public function getTodayCardIds(string $boardId): array;
}
