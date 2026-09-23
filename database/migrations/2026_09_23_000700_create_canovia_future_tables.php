<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_features', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 120)->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedInteger('threshold')->default(500);
            $table->string('status', 40)->default('voting')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('voting_enabled')->default(true);
            $table->timestamp('released_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('roadmap_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 120)->nullable()->index();
            $table->string('voter_key', 160);
            $table->timestamps();

            $table->unique(['roadmap_feature_id', 'voter_key']);
        });

        $now = now();
        DB::table('roadmap_features')->insert([
            [
                'feature_key' => 'social.progress_feed',
                'title' => '他の人の頑張りを見たい',
                'description' => '公開を選んだ人の進捗や達成を見て、自分も前へ進むきっかけにできる体験です。',
                'threshold' => 500,
                'status' => 'voting',
                'sort_order' => 10,
                'is_published' => true,
                'voting_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'feature_key' => 'social.goal_peers',
                'title' => '同じ目標の人を見つけたい',
                'description' => '同じ資格・制作・挑戦に取り組む人を見つけ、孤独になりにくくする体験です。',
                'threshold' => 500,
                'status' => 'voting',
                'sort_order' => 20,
                'is_published' => true,
                'voting_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'feature_key' => 'social.achievement_sharing',
                'title' => 'Achievementを共有したい',
                'description' => '積み上げや節目を、公開範囲を選びながら共有できる体験です。',
                'threshold' => 500,
                'status' => 'voting',
                'sort_order' => 30,
                'is_published' => true,
                'voting_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'feature_key' => 'social.cheer',
                'title' => '頑張っている人を応援したい',
                'description' => 'コメント中心のSNSではなく、軽いCheerで努力を後押しできる体験です。',
                'threshold' => 500,
                'status' => 'voting',
                'sort_order' => 40,
                'is_published' => true,
                'voting_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'feature_key' => 'economy.gift',
                'title' => 'Gift機能がほしい',
                'description' => '将来、頑張っている人へCanovia内の利用価値を贈れる仕組みの需要を確認します。',
                'threshold' => 500,
                'status' => 'voting',
                'sort_order' => 50,
                'is_published' => true,
                'voting_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'feature_key' => 'ai.automatic_plan_adjustment',
                'title' => 'AIにもっと自動で計画修正してほしい',
                'description' => '確認可能性を保ちながら、計画の見直しや次の行動提案をさらに自動化する方向です。',
                'threshold' => 500,
                'status' => 'voting',
                'sort_order' => 60,
                'is_published' => true,
                'voting_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_votes');
        Schema::dropIfExists('roadmap_features');
    }
};
