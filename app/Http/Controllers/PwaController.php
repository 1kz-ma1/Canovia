<?php

namespace App\Http\Controllers;

use App\Services\PwaHandoffService;
use Illuminate\Http\Request;

class PwaController extends Controller
{
    public function manifest(Request $request, PwaHandoffService $handoffService)
    {
        $token = $handoffService->create($request);
        $startUrl = route('pwa.handoff', ['token' => $token], false);

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
                'handoffUrl' => route('pwa.handoff', ['token' => $token]),
            ])
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function handoff(Request $request, string $token, PwaHandoffService $handoffService)
    {
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
            return redirect()->route('home')->with('status', '引き継ぎリンクの期限が切れました。必要なら旧URLからもう一度アクセスしてください。');
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
