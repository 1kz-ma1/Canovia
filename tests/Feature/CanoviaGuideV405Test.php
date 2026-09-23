<?php

namespace Tests\Feature;

use Tests\TestCase;

class CanoviaGuideV405Test extends TestCase
{
    public function test_guide_catalog_contains_core_and_collaboration_guides(): void
    {
        $catalog = config('canovia_guides');

        $this->assertSame(1, $catalog['version']);
        $this->assertArrayHasKey('together', $catalog['categories']);

        foreach ([
            'first_plan',
            'today_action',
            'timer',
            'plan_update',
            'study_practice',
            'collaboration_create',
            'collaboration_join',
            'resources',
        ] as $key) {
            $this->assertArrayHasKey($key, $catalog['guides']);
            $this->assertNotEmpty($catalog['guides'][$key]['steps']);
        }

        $this->assertSame('together', $catalog['guides']['collaboration_create']['category']);
        $this->assertSame('together', $catalog['guides']['collaboration_join']['category']);
        $this->assertTrue($catalog['guides']['collaboration_create']['requires_auth']);
        $this->assertTrue($catalog['guides']['collaboration_join']['requires_auth']);
    }

    public function test_global_layout_exposes_guide_entry_before_mobile_release_notes(): void
    {
        $view = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('data-guide-open', $view);
        $this->assertStringContainsString('mobile-guide-action', $view);
        $this->assertStringContainsString("layouts.partials.guide", $view);
        $this->assertLessThan(
            strpos($view, 'mobile-release-action'),
            strpos($view, 'mobile-guide-action')
        );
    }

    public function test_guide_partial_has_search_categories_and_spotlight_runner(): void
    {
        $view = file_get_contents(resource_path('views/layouts/partials/guide.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('data-guide-search', $view);
        $this->assertStringContainsString('data-guide-category', $view);
        $this->assertStringContainsString('data-guide-start', $view);
        $this->assertStringContainsString('data-guide-runner', $view);
        $this->assertStringContainsString('data-guide-focus-ring', $view);
        $this->assertStringContainsString('data-guide-prev', $view);
        $this->assertStringContainsString('data-guide-next', $view);
        $this->assertStringContainsString('共同計画', $view);
    }

    public function test_runner_exposes_future_achievement_entrypoint_and_cross_screen_storage(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('window.CanoviaGuide', $script);
        $this->assertStringContainsString('start: startGuide', $script);
        $this->assertStringContainsString('[data-guide-start]', $script);
        $this->assertStringContainsString('canovia.guide.active.v', $script);
        $this->assertStringContainsString('canovia.guide.completed.v', $script);
        $this->assertStringContainsString('scrollIntoView', $script);
        $this->assertStringContainsString('data-onboarding-target', $script);
    }

    public function test_initial_guide_targets_exist_on_real_surfaces(): void
    {
        $files = [
            'views/dashboard/index.blade.php',
            'views/plans/create.blade.php',
            'views/navigation/index.blade.php',
            'views/work_sessions/active.blade.php',
            'views/roadmap/index.blade.php',
            'views/plans/show.blade.php',
            'views/plans/review_assistant.blade.php',
            'views/study_practice/show.blade.php',
            'views/my_plans/index.blade.php',
            'views/plans/join.blade.php',
            'views/plans/collaboration.blade.php',
            'views/resources/index.blade.php',
        ];

        $combined = collect($files)
            ->map(fn (string $path) => file_get_contents(resource_path($path)))
            ->implode("\n");

        foreach ([
            'create-plan',
            'plan-form',
            'today-start',
            'work-timer',
            'plan-update',
            'plan-update-input',
            'plan-detail',
            'study-practice',
            'practice-strategy',
            'collaboration-settings',
            'collaboration-primary',
            'collaboration-join',
            'collaboration-code',
            'plan-resources',
            'resource-add',
        ] as $target) {
            $this->assertTrue(
                str_contains($combined, 'data-guide-target="'.$target.'"')
                    || str_contains($combined, 'data-onboarding-target="'.$target.'"'),
                'Missing Canovia Guide target: '.$target
            );
        }
    }
}
