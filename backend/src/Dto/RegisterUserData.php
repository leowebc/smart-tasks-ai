<?php

namespace App\Dto;

final class RegisterUserData
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
    ) {
    }
}
