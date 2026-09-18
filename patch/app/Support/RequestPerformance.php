<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;

class RequestPerformance
{
    public const ATTRIBUTE = 'canovia.performance';

    public int $queryCount = 0;
    public float $queryMs = 0;
    public int $sessionQueries = 0;
    public float $sessionMs = 0;
    public array $shapes = [];

    public function add(QueryExecuted $event): void
    {
        $this->queryCount++;
        $this->queryMs += $event->time;
        $sessionTable = preg_quote((string) config('session.table', 'sessions'), '/');
        if (preg_match('/\b(?:from|into|update)\s+["`]?'.$sessionTable.'["`]?(?:\s|$)/i', $event->sql)) {
            $this->sessionQueries++;
            $this->sessionMs += $event->time;
        }
        // Shape repetition includes different bindings: an N+1 indicator,
        // not proof of identical queries. Bound values are never retained.
        $shape = hash('sha256', $event->sql);
        if (isset($this->shapes[$shape])) {
            $this->shapes[$shape]['count']++;
            $this->shapes[$shape]['ms'] += $event->time;
        } elseif (count($this->shapes) < 100) {
            $this->shapes[$shape] = ['count' => 1, 'ms' => $event->time];
        }
    }
}
