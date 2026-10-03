<?php

namespace App\Service\Document;

interface TextExtractorInterface
{
    public function supports(string $extension): bool;

    public function extract(string $absolutePath): string;
}
