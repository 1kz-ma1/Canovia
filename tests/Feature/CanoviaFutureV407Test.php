<?php

namespace Tests\Feature;

use App\Enums\FeatureKey;
use App\Models\RoadmapFeature;
use App\Models\RoadmapVote;
use App\Services\FeatureAccessService;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanoviaFutureV407Test extends TestCase
{
    use RefreshDatabase;

    public function test_future_page_is_the_primary_feedback_experience(): void
    {
        $this->get(route('feedback.index'))
            ->assertOk()
            ->assertSee('Canovia Future')
            ->assertSee('他の人の頑張りを見たい')
            ->assertSee('500 Support')
            ->assertSee('その他のフィードバック');
    }

    public function test_guest_actor_can_support_each_candidate_only_once(): void
    {
        $feature = RoadmapFeature::query()->where('feature_key', 'social.goal_peers')->firstOrFail();

        $this->post(route('feedback.future.support', $feature))->assertRedirect();
        $this->post(route('feedback.future.support', $feature))->assertRedirect();

        $this->assertSame(1, RoadmapVote::query()->where('roadmap_feature_id', $feature->id)->count());

        $this->delete(route('feedback.future.unsupport', $feature))->assertRedirect();
        $this->assertSame(0, RoadmapVote::query()->where('roadmap_feature_id', $feature->id)->count());
    }

    public function test_hidden_roadmap_candidate_is_not_exposed(): void
    {
        $feature = RoadmapFeature::query()->where('feature_key', 'social.cheer')->firstOrFail();
        $feature->update(['is_published' => false]);

        $this->get(route('feedback.index'))
            ->assertOk()
            ->assertDontSee($feature->title);

        $this->post(route('feedback.future.support', $feature))->assertNotFound();
    }

    public function test_feature_flag_boundary_remains_separate_from_entitlement(): void
    {
        config()->set('features.flags.'.FeatureKey::AutomaticAiExecution->value, [
            'enabled' => false,
            'environment' => null,
            'platform' => 'all',
            'minimum_app_version' => null,
        ]);

        $this->assertFalse(app(FeatureFlagService::class)->isEnabled(FeatureKey::AutomaticAiExecution));
        $this->assertTrue(app(FeatureAccessService::class)->canUse(null, FeatureKey::AutomaticAiExecution));
    }

    public function test_feature_flag_can_limit_platform_and_minimum_app_version_without_entitlement_logic(): void
    {
        config()->set('features.flags.'.FeatureKey::AutomaticAiExecution->value, [
            'enabled' => true,
            'environment' => 'testing',
            'platform' => 'ios',
            'minimum_app_version' => '40.7.0',
        ]);

        $flags = app(FeatureFlagService::class);

        $this->assertFalse($flags->isEnabled(FeatureKey::AutomaticAiExecution, [
            'platform' => 'web',
            'app_version' => '40.7.0',
        ]));
        $this->assertFalse($flags->isEnabled(FeatureKey::AutomaticAiExecution, [
            'platform' => 'ios',
            'app_version' => '40.6.9',
        ]));
        $this->assertTrue($flags->isEnabled(FeatureKey::AutomaticAiExecution, [
            'platform' => 'ios',
            'app_version' => '40.7.0',
        ]));
    }
}
