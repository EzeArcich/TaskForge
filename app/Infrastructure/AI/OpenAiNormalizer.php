<?php

namespace App\Infrastructure\AI;

use App\Application\Contracts\AiNormalizerInterface;
use App\Exceptions\NormalizationFailedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiNormalizer implements AiNormalizerInterface
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('dailypro.openai.api_key');
        $this->model = config('dailypro.openai.model', 'gpt-4o-mini');
    }

    public function normalize(string $planText, string $timezone, string $startDate): array
    {
        $systemPrompt = $this->buildSystemPrompt($timezone, $startDate);

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

        if ($response->failed()) {
            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
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
}
