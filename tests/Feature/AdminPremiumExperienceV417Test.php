<?php

namespace Tests\Feature;

use App\Enums\EntitlementSource;
use App\Enums\FeatureKey;
use App\Enums\ProductKey;
use App\Models\User;
use App\Models\UserProductGrant;
use App\Services\AdminPreviewContext;
use App\Services\AiCapacityService;
use App\Services\FeatureAccessService;
use App\Services\ProductGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPremiumExperienceV417Test extends TestCase
{
    use RefreshDatabase;

    public function test_only_configured_super_admin_account_can_open_admin_routes(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        config([
            'canovia.super_admin_user_id' => $admin->id,
            'canovia.admin_email' => $other->email,
        ]);

        $this->actingAs($other)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($other)
            ->post(route('admin.authenticate'), ['password' => 'anything'])
            ->assertForbidden();
    }

    public function test_settings_exposes_admin_entry_only_to_super_admin(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('設定')
            ->assertSee('管理者メニュー')
            ->assertSee('Super Admin');

        $this->actingAs($other)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('設定')
            ->assertDontSee('管理者メニュー')
            ->assertDontSee('SUPER ADMIN');
    }

    public function test_super_admin_gets_all_feature_access_and_boosted_capacity_without_product_grants(): void
    {
        $admin = User::factory()->create();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $access = app(FeatureAccessService::class);

        foreach (FeatureKey::cases() as $feature) {
            $decision = $access->resolveAccess($admin, $feature);

            $this->assertTrue($decision->allowed, $feature->value);
            $this->assertSame(EntitlementSource::Admin, $decision->source);
            $this->assertSame('super_admin', $decision->reason);
        }

        $this->assertSame('boosted', app(AiCapacityService::class)->tierFor($admin));
        $this->assertDatabaseCount('user_product_grants', 0);
    }

    public function test_admin_preview_changes_feature_context_without_removing_admin_route_access(): void
    {
        $admin = User::factory()->create();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('admin.preview.update'), ['mode' => 'free'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.economy.index', ['user_id' => $admin->id]))
            ->assertOk()
            ->assertSessionHas(AdminPreviewContext::SESSION_KEY, 'free');

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Free プレビュー')
            ->assertSee('管理者メニュー');

        $this->actingAs($admin)
            ->post(route('admin.preview.update'), ['mode' => 'premium'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Premium プレビュー')
            ->assertSee('管理者メニュー');

        $this->actingAs($admin)
            ->post(route('admin.preview.update'), ['mode' => 'admin'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Super Admin')
            ->assertSessionMissing(AdminPreviewContext::SESSION_KEY);
    }

    public function test_complimentary_premium_grants_only_premium_core_and_never_admin_access(): void
    {
        $admin = User::factory()->create();
        $friend = User::factory()->create();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('admin.economy.complimentary.store'), [
                'user_id' => $friend->id,
                'duration' => '30_days',
            ])
            ->assertRedirect(route('admin.economy.index', ['user_id' => $friend->id]));

        $grant = UserProductGrant::query()
            ->where('user_id', $friend->id)
            ->where('source', 'complimentary')
            ->firstOrFail();

        $this->assertSame(ProductKey::PremiumCore, $grant->product_key);
        $this->assertNotNull($grant->expires_at);
        $this->assertTrue($grant->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertTrue(app(ProductGrantService::class)->hasEffectiveProduct($friend, ProductKey::PremiumCore));
        $this->assertFalse(app(ProductGrantService::class)->hasEffectiveProduct($friend, ProductKey::AllAccess));

        $decision = app(FeatureAccessService::class)->resolveAccess($friend, FeatureKey::AutomaticAiExecution);
        $this->assertTrue($decision->allowed);
        $this->assertSame(EntitlementSource::Premium, $decision->source);
        $this->assertSame('complimentary', data_get($decision->metadata, 'grant_source'));

        $this->actingAs($friend)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_complimentary_premium_can_be_revoked_without_deleting_history(): void
    {
        $admin = User::factory()->create();
        $friend = User::factory()->create();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('admin.economy.complimentary.store'), [
                'user_id' => $friend->id,
                'duration' => 'unlimited',
            ])
            ->assertRedirect();

        $grant = UserProductGrant::query()->where('source', 'complimentary')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.economy.complimentary.destroy', $friend))
            ->assertRedirect(route('admin.economy.index', ['user_id' => $friend->id]));

        $grant->refresh();
        $this->assertNotNull($grant->expires_at);
        $this->assertSame('admin_complimentary_premium', data_get($grant->metadata, 'revoked_via'));
        $this->assertFalse(app(ProductGrantService::class)->hasEffectiveProduct($friend, ProductKey::PremiumCore));
        $this->assertDatabaseHas('user_product_grants', ['id' => $grant->id]);
    }
}
