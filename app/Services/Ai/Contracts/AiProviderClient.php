<?php

namespace App\Services\Ai\Contracts;

interface AiProviderClient
{
    public function provider(): string;

    public function label(): string;

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function listModels(string $apiKey): array;

    /**
     * Return a JSON object from a chat completion.
     *
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public function completeJson(
        string $apiKey,
        string $model,
        string $system,
        string $user,
        array $jsonSchema,
        int $maxOutputTokens = 16384,
    ): array;
}
