<?php

namespace App\Services\Ai;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

class ProviderHttp
{
    public static function throwForStatus(Response $response, string $provider): void
    {
        $status = $response->status();

        if ($status < 400) {
            return;
        }

        $json = $response->json();
        $message = $provider === 'gemini'
            ? self::geminiMessage($status, $json)
            : self::genericMessage($provider, $status, $json);

        if ($status === 429 || $status >= 500) {
            throw new TransientAnalysisException($message);
        }

        throw new PermanentAnalysisException($message);
    }

    public static function wrapConnection(ConnectionException $exception): TransientAnalysisException
    {
        return new TransientAnalysisException('Analysis provider timed out.', 0, $exception);
    }

    /**
     * @param  mixed  $json
     */
    public static function errorCode(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $message = data_get($json, 'error.message');
        if (is_scalar($message) && trim((string) $message) !== '') {
            return substr((string) $message, 0, 240);
        }

        foreach (['status', 'type', 'code'] as $key) {
            $value = data_get($json, 'error.'.$key) ?? ($json[$key] ?? null);
            if (! is_scalar($value) || (string) $value === '' || is_numeric($value)) {
                continue;
            }

            return substr((string) $value, 0, 120);
        }

        return null;
    }

    /**
     * @param  mixed  $json
     */
    private static function geminiMessage(int $httpStatus, mixed $json): string
    {
        $geminiStatus = self::nonNumericScalar(data_get($json, 'error.status'));
        $geminiMessage = self::scalarText(data_get($json, 'error.message'), 400);
        $geminiCode = self::nonNumericScalar(data_get($json, 'error.code'));
        $geminiDetails = self::geminiDetails($json);

        $prefix = 'Gemini HTTP '.$httpStatus;
        if ($geminiStatus !== null) {
            $prefix .= ' '.$geminiStatus;
        } elseif ($geminiCode !== null) {
            $prefix .= ' '.$geminiCode;
        }

        $suffix = trim(implode(' ', array_filter([$geminiMessage, $geminiDetails])));

        return $suffix === '' ? $prefix.'.' : $prefix.': '.$suffix;
    }

    /**
     * @param  mixed  $json
     */
    private static function geminiDetails(mixed $json): ?string
    {
        $details = data_get($json, 'error.details');
        if (! is_array($details) || $details === []) {
            return null;
        }

        $parts = [];
        array_walk_recursive($details, function (mixed $value, mixed $key) use (&$parts): void {
            if (! is_string($key) || ! in_array($key, ['description', 'field', 'reason'], true)) {
                return;
            }
            if (! is_scalar($value) || is_bool($value)) {
                return;
            }
            $text = trim((string) $value);
            if ($text === '') {
                return;
            }
            $parts[] = $key.'='.$text;
        });

        if ($parts === []) {
            $encoded = json_encode($details, JSON_UNESCAPED_UNICODE);

            return is_string($encoded) ? substr($encoded, 0, 400) : null;
        }

        return substr(implode('; ', $parts), 0, 400);
    }

    /**
     * @param  mixed  $json
     */
    private static function genericMessage(string $provider, int $status, mixed $json): string
    {
        $detail = self::errorCode($json);
        $verb = ($status === 429 || $status >= 500) ? 'returned HTTP' : 'rejected the request with HTTP';

        return ucfirst($provider).' '.$verb.' '.$status.($detail ? " ({$detail})" : '').'.';
    }

    /**
     * @param  mixed  $value
     */
    private static function nonNumericScalar(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value) || is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : substr($text, 0, 120);
    }

    /**
     * @param  mixed  $value
     */
    private static function scalarText(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : substr($text, 0, $limit);
    }
}
