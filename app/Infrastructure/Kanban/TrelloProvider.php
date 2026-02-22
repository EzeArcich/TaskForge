<?php

namespace App\Infrastructure\Kanban;

use App\Application\Contracts\KanbanProviderInterface;
use App\Models\Plan;
use App\Models\PlanTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrelloProvider implements KanbanProviderInterface
{
    private string $key;
    private string $token;
    private string $baseUrl = 'https://api.trello.com/1';

    private const LISTS = ['Backlog', 'Esta semana', 'Hoy', 'Hecho'];

    public function __construct()
    {
        $this->key = config('dailypro.trello.key');
        $this->token = config('dailypro.trello.token');
    }

    public function createBoard(Plan $plan): array
    {
        $title = $plan->normalized_json['title'] ?? 'DailyPro Plan #' . $plan->id;

        // Create board
        $board = $this->request('POST', '/boards', [
            'name' => $title,
            'defaultLists' => 'false',
        ]);

        $boardId = $board['id'];
        $boardUrl = $board['url'] ?? $board['shortUrl'] ?? '';

        // Create lists (in reverse order so they appear correctly)
        $listIds = [];
        foreach (array_reverse(self::LISTS) as $listName) {
            $list = $this->request('POST', '/lists', [
                'name' => $listName,
                'idBoard' => $boardId,
            ]);
            $listIds[$listName] = $list['id'];
        }

        // Create cards in Backlog
        $cardIds = [];
        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            $card = $this->request('POST', '/cards', [
                'name' => $task->title,
                'idList' => $listIds['Backlog'],
                'desc' => sprintf('Estimate: %.1fh | Week: %d', $task->estimate_hours, $task->week?->week_number ?? 0),
                'due' => $this->buildDueDateTime($plan, $task),
            ]);
            $cardIds[$task->id] = $card['id'];
        }

        Log::info('Trello board created', [
            'plan_id' => $plan->id,
            'board_id' => $boardId,
            'cards_created' => count($cardIds),
        ]);

        return [
            'board_id' => $boardId,
            'board_url' => $boardUrl,
            'card_ids' => $cardIds,
        ];
    }

    public function moveCard(string $cardId, string $listName): void
    {
        $card = $this->request('GET', "/cards/{$cardId}");
        $boardId = $card['idBoard'];

        $lists = $this->request('GET', "/boards/{$boardId}/lists");
        $targetList = collect($lists)->firstWhere('name', $listName);

        if (! $targetList) {
            Log::warning('Target list not found on Trello board', ['list_name' => $listName, 'board_id' => $boardId]);
            return;
        }

        $this->request('PUT', "/cards/{$cardId}", [
            'idList' => $targetList['id'],
        ]);
    }

    public function updateCardDueDates(Plan $plan): void
    {
        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            if ($task->trello_card_id && $task->scheduled_date) {
                $this->request('PUT', "/cards/{$task->trello_card_id}", [
                    'due' => $this->buildDueDateTime($plan, $task),
                ]);
            }
        }
    }

    private function buildDueDateTime(Plan $plan, PlanTask $task): ?string
    {
        if (! $task->scheduled_date) {
            return null;
        }

        $timezone = $plan->settings['timezone'] ?? 'UTC';
        $time = $task->scheduled_start ?: '09:00:00';

        return Carbon::parse($task->scheduled_date->format('Y-m-d') . ' ' . $time, $timezone)
            ->utc()
            ->toIso8601String();
    }

    public function getTodayCardIds(string $boardId): array
    {
        $lists = $this->request('GET', "/boards/{$boardId}/lists");
        $todayList = collect($lists)->firstWhere('name', 'Hoy');

        if (! $todayList) {
            return [];
        }

        $cards = $this->request('GET', "/lists/{$todayList['id']}/cards");

        return array_map(fn ($c) => $c['id'], $cards);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;
        $query = ['key' => $this->key, 'token' => $this->token];

        $response = Http::retry(2, 500);

        if ($method === 'GET') {
            $response = $response->get($url, array_merge($query, $data));
        } elseif ($method === 'POST') {
            $response = $response->post($url . '?' . http_build_query($query), $data);
        } elseif ($method === 'PUT') {
            $response = $response->put($url . '?' . http_build_query($query), $data);
        }

        $response->throw();

        return $response->json() ?? [];
    }
}
