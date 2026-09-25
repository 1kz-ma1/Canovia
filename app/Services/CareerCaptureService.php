<?php

namespace App\Services;

use App\Models\CareerCapture;
use App\Models\CareerCapturePayload;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class CareerCaptureService
{
    /**
     * Shared intake boundary for screenshots today and email/calendar/AI
     * connectors later.
     *
     * @param array<string,mixed>|null $extractedData
     */
    public function record(
        Plan $plan,
        string $sourceType,
        ?string $sourceUrl = null,
        ?string $screenshotPath = null,
        ?string $screenshotMime = null,
        ?string $screenshotOriginalName = null,
        ?string $screenshotData = null,
        ?int $screenshotByteSize = null,
        ?string $rawText = null,
        ?array $extractedData = null,
        ?float $confidence = null,
        ?int $userId = null,
        ?string $actorToken = null,
    ): CareerCapture {
        return DB::transaction(function () use (
            $plan,
            $sourceType,
            $sourceUrl,
            $screenshotPath,
            $screenshotMime,
            $screenshotOriginalName,
            $screenshotData,
            $screenshotByteSize,
            $rawText,
            $extractedData,
            $confidence,
            $userId,
            $actorToken,
        ) {
            $capture = CareerCapture::create([
                'plan_id' => (int) $plan->id,
                'user_id' => $userId,
                'actor_token' => $userId ? null : $actorToken,
                'source_type' => $sourceType,
                'status' => 'pending',
                'source_url' => $sourceUrl,
                'screenshot_path' => $screenshotPath,
                'screenshot_mime' => $screenshotMime,
                'screenshot_original_name' => $screenshotOriginalName,
                'raw_text' => $rawText,
                'extracted_data' => $extractedData,
                'confidence' => $confidence,
                'captured_at' => now(),
            ]);

            if ($screenshotData !== null) {
                CareerCapturePayload::create([
                    'career_capture_id' => $capture->id,
                    'screenshot_data' => $screenshotData,
                    'byte_size' => max(0, (int) ($screenshotByteSize ?? 0)),
                ]);
            }

            return $capture;
        });
    }
}
