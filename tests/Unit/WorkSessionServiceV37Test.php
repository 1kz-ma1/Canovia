<?php

namespace Tests\Unit;

use App\Models\WorkSession;
use App\Services\WorkSessionService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class WorkSessionServiceV37Test extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reviewed_break_is_removed_from_active_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00'));
        $session = $this->session('2026-09-19 10:00:00');

        $metrics = (new WorkSessionService())->previewFinish($session, [
            'additional_paused_seconds' => 3600,
        ]);

        $this->assertSame(7200, $metrics['wall_seconds']);
        $this->assertSame(3600, $metrics['paused_seconds']);
        $this->assertSame(3600, $metrics['active_seconds']);
    }

    public function test_manual_correction_keeps_wall_time_accounting_consistent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00'));
        $session = $this->session('2026-09-19 10:00:00');

        $metrics = (new WorkSessionService())->previewFinish($session, [
            'manual_active_seconds' => 45 * 60,
        ]);

        $this->assertSame(7200, $metrics['wall_seconds']);
        $this->assertSame(2700, $metrics['active_seconds']);
        $this->assertSame(4500, $metrics['paused_seconds']);
        $this->assertSame($metrics['wall_seconds'], $metrics['active_seconds'] + $metrics['paused_seconds']);
    }

    public function test_session_can_finish_at_the_time_user_left(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00'));
        $session = $this->session('2026-09-19 10:00:00');

        $metrics = (new WorkSessionService())->previewFinish($session, [
            'ended_at' => Carbon::parse('2026-09-19 10:30:00'),
        ]);

        $this->assertSame(1800, $metrics['wall_seconds']);
        $this->assertSame(1800, $metrics['active_seconds']);
        $this->assertSame('2026-09-19 10:30:00', $metrics['ended_at']->format('Y-m-d H:i:s'));
    }

    public function test_long_session_confirmation_threshold_uses_intended_time_with_floor(): void
    {
        $service = new WorkSessionService();

        $short = $this->session('2026-09-19 10:00:00', 25);
        $longer = $this->session('2026-09-19 10:00:00', 60);
        $unspecified = $this->session('2026-09-19 10:00:00', null);

        $this->assertSame(90 * 60, $service->durationConfirmationThresholdSeconds($short));
        $this->assertSame(120 * 60, $service->durationConfirmationThresholdSeconds($longer));
        $this->assertSame(120 * 60, $service->durationConfirmationThresholdSeconds($unspecified));
    }

    private function session(string $startedAt, ?int $intendedMinutes = 25): WorkSession
    {
        return new WorkSession([
            'status' => 'active',
            'started_at' => Carbon::parse($startedAt),
            'paused_seconds' => 0,
            'intended_minutes' => $intendedMinutes,
        ]);
    }
}
