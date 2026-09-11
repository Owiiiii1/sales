<?php

namespace App\Services\Ai;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;

class JsonPayloadParser
{
    /**
     * @return array<string, mixed>
     */
    public static function parse(string $raw): array
    {
        $trimmed = trim($raw);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();

            if (self::looksTruncated($trimmed)) {
                throw new TransientAnalysisException(
                    'Analysis JSON was truncated before a complete object was returned ('.$error.'). Retry the analysis.'
                );
            }

            throw new TransientAnalysisException(
                'Analysis provider returned invalid JSON ('.$error.'). Retry the analysis.'
            );
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new PermanentAnalysisException('Analysis provider returned JSON that is not an object.');
        }

        return $decoded;
    }

    private static function looksTruncated(string $json): bool
    {
        if ($json === '' || ! str_starts_with($json, '{')) {
            return false;
        }

        if (! str_ends_with($json, '}')) {
            return true;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }
                if ($char === '\\') {
                    $escape = true;

                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }
        }

        return $inString || $depth > 0;
    }
}
