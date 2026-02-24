<?php

namespace EvrenOnur\SanalPos\DTOs;

class Payment3DConfig
{
    public function __construct(
        public bool $confirm = false,
        public string $return_url = '',
        public bool $is_desktop = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            confirm: (bool) ($data['confirm'] ?? false),
            return_url: $data['return_url'] ?? '',
            is_desktop: (bool) ($data['is_desktop'] ?? true),
        );
    }
}
