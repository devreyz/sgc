<?php

namespace App\ValueObjects;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public ?string $name = null,
        public ?string $avatarUrl = null,
    ) {}
}
