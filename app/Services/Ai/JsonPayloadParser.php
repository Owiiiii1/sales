<?php

namespace App\Services\Ai;

use App\Exceptions\Analysis\PermanentAnalysisException;

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

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new PermanentAnalysisException('Analysis provider returned JSON that is not an object.');
        }

        return $decoded;
    }
}
