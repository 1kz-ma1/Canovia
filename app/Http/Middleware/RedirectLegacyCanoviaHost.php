<?php

namespace App\Http\Middleware;

use App\Services\PwaHandoffService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyCanoviaHost
{
    public function __construct(private readonly PwaHandoffService $handoffService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production') || ! config('canovia.redirect_legacy_hosts', false)) {
            return $next($request);
        }

        $canonicalUrl = rtrim((string) config('canovia.canonical_url', ''), '/');
        $canonicalHost = strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST));
        $requestHost = strtolower($request->getHost());
        $legacyHosts = array_map('strtolower', (array) config('canovia.legacy_hosts', []));

        if ($canonicalUrl === '' || $canonicalHost === '' || $requestHost === $canonicalHost || ! in_array($requestHost, $legacyHosts, true)) {
            return $next($request);
        }

        // Only migrate top-level browser navigations. Assets, manifests, health
        // checks and API/fetch requests must keep returning their expected format.
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if (! $request->acceptsHtml() || $request->expectsJson()) {
            return $next($request);
        }

        $fetchDestination = strtolower((string) $request->header('Sec-Fetch-Dest', ''));
        if ($fetchDestination !== '' && $fetchDestination !== 'document') {
            return $next($request);
        }

        $rawToken = $this->handoffService->create($request);
        $nextPath = '/'.ltrim($request->getRequestUri(), '/');
        $target = $canonicalUrl
            .'/pwa/handoff/'.rawurlencode($rawToken)
            .'?brand_migration=1&next='.rawurlencode($nextPath);

        return redirect()->away($target, 302, [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
