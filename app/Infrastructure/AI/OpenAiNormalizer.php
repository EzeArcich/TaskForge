<?php

namespace App\Infrastructure\AI;

use App\Application\Contracts\AiNormalizerInterface;
use App\Exceptions\NormalizationFailedException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiNormalizer implements AiNormalizerInterface
{
    private string $apiKey;
    private string $model;
    private bool $quotaFallbackEnabled;

    public function __construct()
    {
        $this->apiKey = config('dailypro.openai.api_key');
        $this->model = config('dailypro.openai.model', 'gpt-4o-mini');
        $this->quotaFallbackEnabled = (bool) config('dailypro.openai.quota_fallback_enabled', false);
    }

    public function normalize(string $planText, string $timezone, string $startDate): array
    {
        if ($this->quotaFallbackEnabled && blank($this->apiKey)) {
            Log::warning('OpenAI API key missing. Using quota fallback normalizer.');
            return $this->buildFallbackPlan($planText, $timezone, $startDate);
        }

        $systemPrompt = $this->buildSystemPrompt($timezone, $startDate);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(60)
                ->retry(2, 1000)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $planText],
                    ],
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (RequestException $e) {
            $status = $e->response?->status();
            if ($this->quotaFallbackEnabled && $status === 429) {
                Log::warning('OpenAI quota exceeded (exception). Using fallback normalizer.');
                return $this->buildFallbackPlan($planText, $timezone, $startDate);
            }

            throw new NormalizationFailedException($e->getMessage());
        }

        if ($response->failed()) {
            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($this->quotaFallbackEnabled && $response->status() === 429) {
                Log::warning('OpenAI quota exceeded. Using fallback normalizer.');
                return $this->buildFallbackPlan($planText, $timezone, $startDate);
            }

            throw new NormalizationFailedException('OpenAI API returned status ' . $response->status());
        }

        $content = $response->json('choices.0.message.content');
        if (! $content) {
            throw new NormalizationFailedException('OpenAI returned empty content.');
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new NormalizationFailedException('OpenAI returned invalid JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function buildSystemPrompt(string $timezone, string $startDate): string
    {
        return <<<PROMPT
        You are a plan structuring assistant. Given a free-form plan, roadmap, or study plan text,
        extract and normalize it into a strict JSON structure.

        RULES:
        - Output ONLY valid JSON, no markdown, no extra text.
        - Use the timezone: {$timezone}
        - Use the start_date: {$startDate}
        - Every task MUST have a realistic estimate_hours (minimum 0.25, maximum 40).
        - Group tasks into weeks. If the plan doesn't specify weeks, distribute logically.
        - If the plan is too vague, still create a reasonable structure with what's given.
        - The title should summarize the overall plan.

        REQUIRED JSON STRUCTURE:
        {
          "title": "string - overall plan title",
          "timezone": "{$timezone}",
          "start_date": "{$startDate}",
          "weeks": [
            {
              "week": 1,
              "goal": "string - weekly goal",
              "tasks": [
                {
                  "title": "string - task description",
                  "estimate_hours": 2.0
                }
              ]
            }
          ]
        }
        PROMPT;
    }

    private function buildFallbackPlan(string $planText, string $timezone, string $startDate): array
    {
        $weekCount = $this->extractWeekCount($planText);
        $title = $this->extractTitle($planText);
        $isEnglishPlan = preg_match('/\b(ingles|ingl[eé]s|english)\b/i', $planText) === 1;

        $weeks = [];
        for ($week = 1; $week <= $weekCount; $week++) {
            $goal = $isEnglishPlan
                ? $this->englishGoalForWeek($week, $weekCount)
                : 'Avanzar objetivos de la semana ' . $week;

            $tasks = $isEnglishPlan
                ? [
                    ['title' => 'Clase en vivo con profesor #1', 'estimate_hours' => 1.0],
                    ['title' => 'Clase en vivo con profesor #2', 'estimate_hours' => 1.0],
                    ['title' => 'Listening: CNN 10 o BBC Learning English + notas', 'estimate_hours' => 0.75],
                    ['title' => 'Gramatica + vocabulario (Anki) + speaking breve', 'estimate_hours' => 0.75],
                ]
                : [
                    ['title' => 'Sesion guiada #1', 'estimate_hours' => 1.0],
                    ['title' => 'Sesion guiada #2', 'estimate_hours' => 1.0],
                    ['title' => 'Practica autonoma', 'estimate_hours' => 0.75],
                    ['title' => 'Repaso y autoevaluacion', 'estimate_hours' => 0.75],
                ];

            $weeks[] = [
                'week' => $week,
                'goal' => $goal,
                'tasks' => $tasks,
            ];
        }

        return [
            'title' => $title,
            'timezone' => $timezone,
            'start_date' => $startDate,
            'weeks' => $weeks,
        ];
    }

    private function extractWeekCount(string $planText): int
    {
        if (preg_match('/\b(\d{1,2})\s*semanas?\b/i', $planText, $matches) === 1) {
            return max(1, min((int) $matches[1], 52));
        }

        return 4;
    }

    private function extractTitle(string $planText): string
    {
        if (preg_match('/Objetivo:\s*([^.!\n]+)/iu', $planText, $matches) === 1) {
            return trim($matches[1]);
        }

        return 'Plan estructurado';
    }

    private function englishGoalForWeek(int $week, int $totalWeeks): string
    {
        $phase = max(1, (int) ceil($totalWeeks / 4));

        if ($week <= $phase) {
            return 'Base gramatical y comprension auditiva';
        }
        if ($week <= $phase * 2) {
            return 'Fluidez en conversacion cotidiana';
        }
        if ($week <= $phase * 3) {
            return 'Ingles aplicado a trabajo y contextos reales';
        }

        return 'Consolidacion final y simulaciones practicas';
    }
}
