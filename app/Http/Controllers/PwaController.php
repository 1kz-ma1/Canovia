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
            'name' => 'PaceKeeper',
            'short_name' => 'PaceKeeper',
            'description' => 'いつものAIと計画をつなぎ、今日の一歩まで整理するPaceKeeper',
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

        if ($result['type'] === 'account') {
            return redirect()->route('home')->with('status', 'ログイン状態をこのPaceKeeperアプリへ引き継ぎました。');
        }

        if ($result['type'] === 'guest' && $result['count'] > 0) {
            return redirect()->route('home')->with('status', "ブラウザで使っていた{$result['count']}件の計画をこのアプリへ引き継ぎました。");
        }

        if ($result['type'] === 'invalid') {
            return redirect()->route('home')->with('status', '引き継ぎリンクの期限が切れました。必要ならブラウザからもう一度ホーム画面へ追加してください。');
        }

        return redirect()->route('home');
    }
}
