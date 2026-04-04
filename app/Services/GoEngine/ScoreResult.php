<?php

namespace App\Services\GoEngine;

readonly class ScoreResult
{
    public float $blackTotal;
    public float $whiteTotal;
    public string $winner;
    public string $result;

    public function __construct(
        public int $blackTerritory,
        public int $whiteTerritory,
        public int $blackCaptures,
        public int $whiteCaptures,
        public float $komi,
    ) {
        $this->blackTotal = $blackTerritory + $blackCaptures;
        $this->whiteTotal = $whiteTerritory + $whiteCaptures + $komi;

        if ($this->blackTotal > $this->whiteTotal) {
            $this->winner = 'black';
            $diff = round($this->blackTotal - $this->whiteTotal, 1);
            $this->result = "B+{$diff}";
        } elseif ($this->whiteTotal > $this->blackTotal) {
            $this->winner = 'white';
            $diff = round($this->whiteTotal - $this->blackTotal, 1);
            $this->result = "W+{$diff}";
        } else {
            $this->winner = 'draw';
            $this->result = 'Draw';
        }
    }
}
