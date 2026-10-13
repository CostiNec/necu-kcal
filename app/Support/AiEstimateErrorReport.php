<?php

namespace App\Support;

use Throwable;

class AiEstimateErrorReport
{
    /** @return array{message: string, error_report: string} */
    public static function from(Throwable $exception): array
    {
        $provider = (string) config('nutrition-ai.provider');
        $lines = [
            'AI nutrition estimate error',
            'Time: '.now()->toIso8601String(),
            'Provider: '.$provider,
            'Model: '.(string) config("services.{$provider}.nutrition_model"),
        ];

        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $lines[] = '';
            $lines[] = get_class($current).': '.$current->getMessage();
            $lines[] = $current->getFile().':'.$current->getLine();
            $lines[] = '[stacktrace]';
            // Preserve every frame without including request arguments or credentials.
            foreach ($current->getTrace() as $index => $frame) {
                $lines[] = sprintf(
                    '#%d %s(%s): %s%s%s()',
                    $index,
                    $frame['file'] ?? '[internal]',
                    $frame['line'] ?? '-',
                    $frame['class'] ?? '',
                    $frame['type'] ?? '',
                    $frame['function'] ?? ''
                );
            }
        }

        return [
            'message' => self::redact(explode("\n", $exception->getMessage())[0]),
            'error_report' => self::redact(implode("\n", $lines)),
        ];
    }

    private static function redact(string $text): string
    {
        foreach (['groq.api_key', 'openai.api_key', 'gemini.api_key', 'gemini.api_key_2', 'gemini.api_key_3'] as $key) {
            $secret = (string) config('services.'.$key);
            if ($secret !== '') {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }

        return preg_replace('/\b(?:gsk_|sk-)[a-zA-Z0-9_-]+|Bearer\s+[^\s"\']+/i', '[REDACTED]', $text) ?? $text;
    }
}
