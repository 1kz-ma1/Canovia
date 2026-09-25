<?php

namespace App\Http\Middleware;

use App\Services\AdminAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminAccess
{
    public function __construct(
        private readonly AdminAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->access->authorized($request), 403);

        return $next($request);
    }
}
