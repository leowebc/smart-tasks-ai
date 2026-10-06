<?php

namespace App\Service\Chat;

interface LlmProviderInterface
{
    public function complete(string $system, string $user): string;
}
