<?php

namespace App\Service\Document;

final class TxtTextExtractor implements TextExtractorInterface
{
    public function supports(string $extension): bool
    {
        return $extension === 'txt';
    }

    public function extract(string $absolutePath): string
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \RuntimeException('Não foi possível ler o arquivo TXT.');
        }
        if (str_contains($content, "\0")) {
            throw new \RuntimeException('O arquivo TXT não contém texto válido.');
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new \RuntimeException('O arquivo TXT precisa estar em UTF-8.');
        }

        return $content;
    }
}
