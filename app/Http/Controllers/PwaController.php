<?php

namespace App\Http\Controllers;

use App\Services\PwaHandoffService;
use Illuminate\Http\Request;

class PwaController extends Controller
{
    public function manifest(Request $request)
    {
        // Normal app pages always advertise a stable launch URL. The install
        // guide may supply one short-lived bootstrap token so the *first* PWA
        // launch can transfer Safari identity without making that capability the
        // permanent start URL for every future manifest request.
        $handoffToken = trim((string) $request->query('handoff', ''));
        $validHandoff = $handoffToken !== ''
            && preg_match('/^[A-Za-z0-9]{40,100}$/', $handoffToken) === 1;

        $startUrl = $validHandoff
            ? route('pwa.handoff', ['token' => $handoffToken, 'launch' => 1], false)
            : route('home', [], false);

        return response()->json([
            'id' => '/',
            'name' => 'Canovia',
            'short_name' => 'Canovia',
            'description' => 'いつものAIと計画をつなぎ、今日の一歩まで整理するCanovia',
            'start_url' => $startUrl,
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#0A0F1E',
            'theme_color' => '#0A0F1E',
            'icons' => [
                [
                    'src' => '/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Vary' => 'Cookie',
        ]);
    }

    public function prepareInstall(Request $request, PwaHandoffService $handoffService)
    {
        $token = $handoffService->create($request);

        return redirect()->route('pwa.install.guide', ['token' => $token]);
    }

    public function installGuide(string $token)
    {
        return response()
            ->view('pwa.install', [
                'handoffUrl' => route('pwa.handoff', ['token' => $token, 'launch' => 1]),
                'manifestUrl' => route('pwa.manifest', ['handoff' => $token]),
            ])
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function handoff(Request $request, string $token, PwaHandoffService $handoffService)
    {
        // Some iOS/PWA launch paths probe start_url with HEAD before the real
        // navigation. Never let that probe consume the one-time capability.
        if ($request->isMethod('HEAD')) {
            return response()->noContent()
                ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        }

        $result = $handoffService->consume($request, $token);
        $nextPath = $this->safeNextPath((string) $request->query('next', ''));
        $redirect = $nextPath !== null ? redirect($nextPath) : redirect()->route('home');

        if ($result['type'] === 'account') {
            return $redirect->with('status', $request->boolean('brand_migration')
                ? 'Canoviaの新しいURLへログイン状態を引き継ぎました。'
                : 'ログイン状態をこのCanoviaアプリへ引き継ぎました。');
        }

        if ($result['type'] === 'guest' && $result['count'] > 0) {
            return $redirect->with('status', $request->boolean('brand_migration')
                ? "Canoviaの新しいURLへ{$result['count']}件の計画を引き継ぎました。"
                : "ブラウザで使っていた{$result['count']}件の計画をこのアプリへ引き継ぎました。");
        }

        if ($result['type'] === 'invalid') {
            // An installed app may keep the bootstrap start_url for a while even
            // after the one-time token has been consumed. That is a normal
            // relaunch, not an error: quietly converge on the stable Home URL.
            if ($request->boolean('launch')) {
                return $redirect;
            }

            return redirect()->route('home')->with('status', '引き継ぎリンクの期限が切れました。必要ならもう一度ホーム画面追加の手順を開いてください。');
        }

        return $redirect;
    }

    private function safeNextPath(string $candidate): ?string
    {
        if ($candidate === '' || ! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) {
            return null;
        }

        return $candidate;
    }
}
