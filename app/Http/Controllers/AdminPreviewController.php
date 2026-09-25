<?php

namespace App\Http\Controllers;

use App\Services\AdminPreviewContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminPreviewController extends Controller
{
    public function __construct(
        private readonly AdminPreviewContext $preview,
    ) {}

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['admin', 'free', 'premium'])],
        ]);

        $this->preview->set($request, (string) $validated['mode']);

        $label = match ($validated['mode']) {
            'free' => 'Free',
            'premium' => 'Premium',
            default => 'Admin',
        };

        return back()->with('status', $label.'表示へ切り替えました。');
    }
}
