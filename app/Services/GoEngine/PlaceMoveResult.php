<?php

namespace App\Services\GoEngine;

readonly class PlaceMoveResult
{
    public function __construct(
        public array $newBoard,
        public array $capturedStones,
        public ?string $newKoPoint,
    ) {}
}
