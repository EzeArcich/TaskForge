<?php

namespace App\Application\Contracts;

interface AiNormalizerInterface
{
    /**
     * Normalize free-form plan text into structured JSON.
     *
     * @return array The normalized plan structure
     * @throws \App\Exceptions\NormalizationFailedException
     */
    public function normalize(string $planText, string $timezone, string $startDate): array;
}
