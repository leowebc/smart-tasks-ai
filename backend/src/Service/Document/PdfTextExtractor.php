<?php

namespace App\Service\Document;

use Smalot\PdfParser\Parser;

final class PdfTextExtractor implements TextExtractorInterface
{
    public function supports(string $extension): bool
    {
        return $extension === 'pdf';
    }

    public function extract(string $absolutePath): string
    {
        try {
            $text = (new Parser())->parseFile($absolutePath)->getText();
        } catch (\Throwable) {
            throw new \RuntimeException('Não foi possível extrair o texto do PDF.');
        }

        if (trim($text) === '') {
            throw new \RuntimeException('OCR não está disponível. O PDF não possui texto selecionável.');
        }

        return $text;
    }
}
