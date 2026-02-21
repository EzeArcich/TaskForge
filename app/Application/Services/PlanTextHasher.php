<?php

namespace App\Application\Services;

class PlanTextHasher
{
    /**
     * Generate an idempotency hash from plan text and settings.
     * Normalizes input to ensure consistent hashing.
     */
    public function hash(string $planText, array $settings): string
    {
        $normalizedText = $this->normalizeText($planText);
        $normalizedSettings = $this->normalizeSettings($settings);

        $payload = json_encode([
            'plan_text' => $normalizedText,
            'settings' => $normalizedSettings,
        ], JSON_UNESCAPED_UNICODE);

        return hash('sha256', $payload);
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\r\n/', "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return $text;
    }

    private function normalizeSettings(array $settings): array
    {
        ksort($settings);

        if (isset($settings['availability']) && is_array($settings['availability'])) {
            usort($settings['availability'], fn ($a, $b) => ($a['day'] ?? '') <=> ($b['day'] ?? ''));
        }

        return $settings;
    }
}
