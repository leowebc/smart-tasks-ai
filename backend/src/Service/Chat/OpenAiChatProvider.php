<?php

namespace App\Service\Chat;

final class OpenAiChatProvider implements LlmProviderInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function complete(string $system, string $user): string
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('A chave de chat não está configurada.');
        }
        $body = json_encode([
            'model' => $this->model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer ".$this->apiKey."\r\n",
                'content' => $body,
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents(self::ENDPOINT, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }
        $payload = is_string($response) ? json_decode($response, true) : null;
        $text = is_array($payload) ? ($payload['choices'][0]['message']['content'] ?? null) : null;
        if ($response === false || $status < 200 || $status >= 300 || !is_string($text) || trim($text) === '') {
            throw new \RuntimeException('A API de chat falhou.');
        }

        return trim($text);
    }
}
