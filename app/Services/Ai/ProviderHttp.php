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

        $code = self::errorCode($response->json());

        if ($status === 429 || $status >= 500) {
            throw new TransientAnalysisException(
                ucfirst($provider).' returned HTTP '.$status.($code ? " ({$code})" : '').'.'
            );
        }

        throw new PermanentAnalysisException(
            ucfirst($provider).' rejected the request with HTTP '.$status.($code ? " ({$code})" : '').'.'
        );
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

        foreach (['code', 'type', 'status'] as $key) {
            $value = data_get($json, 'error.'.$key) ?? ($json[$key] ?? null);
            if (is_scalar($value) && (string) $value !== '') {
                return substr((string) $value, 0, 120);
            }
        }

        $message = data_get($json, 'error.message');
        if (is_scalar($message) && (string) $message !== '') {
            return substr((string) $message, 0, 120);
        }

        return null;
    }
}
