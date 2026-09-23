<?php

namespace Tests\Feature;

use Tests\TestCase;

class AiHandoffUiV4043Test extends TestCase
{
    public function test_plan_update_hides_raw_payloads_and_uses_one_click_paste(): void
    {
        $view = file_get_contents(resource_path('views/plans/review_assistant.blade.php'));

        $this->assertIsString($view);
        $this->assertStringNotContainsString('min-h-[420px]', $view);
        $this->assertStringNotContainsString('min-h-[220px]', $view);
        $this->assertStringContainsString('このプロンプトに含まれる情報', $view);
        $this->assertStringContainsString('data-copy-target="#reviewPrompt"', $view);
        $this->assertStringContainsString('data-paste-target="#review_operations_json"', $view);
        $this->assertStringContainsString('data-paste-submit="1"', $view);
        $this->assertStringContainsString('手動で貼り付ける / JSONを確認する', $view);
    }

    public function test_policy_change_surfaces_share_the_same_quiet_handoff_contract(): void
    {
        foreach ([
            resource_path('views/plans/policy_change.blade.php'),
            resource_path('views/chat/partials/policy_change.blade.php'),
        ] as $path) {
            $view = file_get_contents($path);

            $this->assertIsString($view);
            $this->assertStringNotContainsString('min-h-[420px]', $view);
            $this->assertStringContainsString('このプロンプトに含まれる情報', $view);
            $this->assertStringContainsString('クリップボードから貼り付けて確認', $view);
            $this->assertStringContainsString('data-paste-submit="1"', $view);
        }
    }

    public function test_resource_assistant_hides_prompt_and_collapses_json_fallback(): void
    {
        $view = file_get_contents(resource_path('views/resources/assistant.blade.php'));

        $this->assertIsString($view);
        $this->assertStringNotContainsString('min-h-[420px]', $view);
        $this->assertStringContainsString('この相談文に含まれる情報', $view);
        $this->assertStringContainsString('data-copy-target="#resource-assistant-prompt"', $view);
        $this->assertStringContainsString('data-paste-target="#resource_assignment_json"', $view);
        $this->assertStringContainsString('手動で貼り付ける / JSONを確認する', $view);
    }

    public function test_ai_context_prompt_is_not_rendered_as_a_large_text_box(): void
    {
        $view = file_get_contents(resource_path('views/chat/partials/ai_context.blade.php'));

        $this->assertIsString($view);
        $this->assertStringNotContainsString('min-h-[420px]', $view);
        $this->assertStringContainsString('class="sr-only"', $view);
        $this->assertStringContainsString('この共有内容に含まれる情報', $view);
        $this->assertStringContainsString('コピーして共有済みにする', $view);
    }

    public function test_central_clipboard_handler_keeps_one_click_paste_with_manual_fallback(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('[data-paste-target]', $script);
        $this->assertStringContainsString('navigator.clipboard?.readText', $script);
        $this->assertStringContainsString("button.dataset.pasteSubmit === '1'", $script);
        $this->assertStringContainsString('openFallback(message)', $script);
    }
}
