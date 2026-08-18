<?php

namespace App\Contracts;

interface PushProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * @param  list<string>  $deviceTokens
     * @return array{status: string, sent: int, failed: int, detail: string}
     */
    public function send(array $deviceTokens, string $title, string $body, array $data = []): array;
}
