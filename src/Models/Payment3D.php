<?php

namespace EvrenOnur\SanalPos\Models;

class Payment3D
{
    public function __construct(
        public bool $confirm = false,
        public string $returnURL = '',
        public bool $isDesktop = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            confirm: (bool) ($data['confirm'] ?? false),
            returnURL: $data['returnURL'] ?? '',
            isDesktop: (bool) ($data['isDesktop'] ?? true),
        );
    }
}
