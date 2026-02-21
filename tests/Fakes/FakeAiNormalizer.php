<?php

namespace Tests\Fakes;

use App\Application\Contracts\AiNormalizerInterface;
use App\Exceptions\NormalizationFailedException;

class FakeAiNormalizer implements AiNormalizerInterface
{
    private ?array $response = null;
    private bool $shouldFail = false;

    public function setResponse(array $response): self
    {
        $this->response = $response;
        return $this;
    }

    public function shouldFail(bool $fail = true): self
    {
        $this->shouldFail = $fail;
        return $this;
    }

    public function normalize(string $planText, string $timezone, string $startDate): array
    {
        if ($this->shouldFail) {
            throw new NormalizationFailedException('Fake AI normalization failed.');
        }

        if ($this->response) {
            return $this->response;
        }

        return [
            'title' => 'Parsed: ' . substr($planText, 0, 50),
            'timezone' => $timezone,
            'start_date' => $startDate,
            'weeks' => [
                [
                    'week' => 1,
                    'goal' => 'Getting started',
                    'tasks' => [
                        ['title' => 'Setup environment', 'estimate_hours' => 1.5],
                        ['title' => 'Read documentation', 'estimate_hours' => 2.0],
                    ],
                ],
                [
                    'week' => 2,
                    'goal' => 'Deep dive',
                    'tasks' => [
                        ['title' => 'Build first feature', 'estimate_hours' => 3.0],
                        ['title' => 'Write tests', 'estimate_hours' => 1.5],
                    ],
                ],
            ],
        ];
    }
}
