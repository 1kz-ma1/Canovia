<?php

namespace App\Services;

use App\Enums\FeatureKey;
use App\Exceptions\NativeAiExecutionException;
use App\Models\NativeAiRun;
use App\Models\Plan;
use App\Models\StudyPracticeSession;
use App\Models\Task;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class NativeAiGateway
{
    public function isConfigured(): bool
    {
        $driver = (string) config('native_ai.driver', 'disabled');

        if ($driver !== 'openai') {
            return false;
        }

        return trim((string) config('native_ai.providers.openai.api_key', '')) !== ''
            && trim((string) config('native_ai.providers.openai.model', '')) !== '';
    }

    public function provider(): string
    {
        return (string) config('native_ai.driver', 'disabled');
    }

    public function model(): string
    {
        $driver = $this->provider();

        return trim((string) config("native_ai.providers.{$driver}.model", ''));
    }

    /**
     * @return array{data:array<string,mixed>,run_id:int,provider:string,model:string}
     */
    public function generateStructured(
        string $purpose,
        string $prompt,
        array $schema,
        string $schemaName,
        Plan $plan,
        Task $task,
        ?int $maxOutputTokens = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new NativeAiExecutionException(
                'Canovia Native AIは現在設定されていません。',
                'native_ai_not_configured',
            );
        }

        $driver = $this->provider();
        if ($driver !== 'openai') {
            throw new NativeAiExecutionException(
                'Canovia Native AIのprovider設定を確認してください。',
                'native_ai_driver_unsupported',
            );
        }

        $model = $this->model();
        $run = NativeAiRun::query()->create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'feature_key' => FeatureKey::AutomaticAiExecution->value,
            'purpose' => $purpose,
            'provider' => $driver,
            'model' => $model,
            'status' => 'running',
            'request_hash' => hash('sha256', $purpose."\n".$model."\n".$prompt),
            'started_at' => now(),
        ]);

        try {
            $body = $this->callOpenAi(
                prompt: $prompt,
                schema: $schema,
                schemaName: $schemaName,
                model: $model,
                maxOutputTokens: $maxOutputTokens,
            );

            $outputText = $this->extractOutputText($body);
            if ($outputText === '') {
                $refusal = $this->extractRefusal($body);
                $message = $refusal !== ''
                    ? 'Native AIがこのリクエストへの応答を生成できませんでした。'
                    : 'Native AIから構造化結果を取得できませんでした。';

                throw new NativeAiExecutionException(
                    $message,
                    $refusal !== '' ? 'native_ai_refusal' : 'native_ai_empty_output',
                    $run->id,
                );
            }

            $decoded = json_decode($outputText, true);
            if (! is_array($decoded)) {
                throw new NativeAiExecutionException(
                    'Native AIの構造化結果をJSONとして読み取れませんでした。',
                    'native_ai_invalid_json',
                    $run->id,
                );
            }

            $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
            $run->update([
                'status' => 'succeeded',
                'provider_response_id' => filled($body['id'] ?? null) ? (string) $body['id'] : null,
                'input_tokens' => $this->nullablePositiveInt($usage['input_tokens'] ?? null),
                'output_tokens' => $this->nullablePositiveInt($usage['output_tokens'] ?? null),
                'total_tokens' => $this->nullablePositiveInt($usage['total_tokens'] ?? null),
                'metadata' => [
                    'response_status' => $body['status'] ?? null,
                    'schema_name' => $schemaName,
                ],
                'completed_at' => now(),
            ]);

            return [
                'data' => $decoded,
                'run_id' => (int) $run->id,
                'provider' => $driver,
                'model' => $model,
            ];
        } catch (NativeAiExecutionException $exception) {
            $this->markFailed($run, $exception->errorCode, $exception->getMessage());
            throw $exception;
        } catch (ConnectionException $exception) {
            $this->markFailed($run, 'native_ai_connection_failed', $exception->getMessage());

            throw new NativeAiExecutionException(
                'Native AIへ接続できませんでした。',
                'native_ai_connection_failed',
                $run->id,
            );
        } catch (Throwable $exception) {
            $this->markFailed($run, 'native_ai_unexpected_error', $exception->getMessage());

            throw new NativeAiExecutionException(
                'Native AIの実行中にエラーが発生しました。',
                'native_ai_unexpected_error',
                $run->id,
            );
        }
    }

    public function attachRun(int $runId, ?int $userId, StudyPracticeSession $session): void
    {
        NativeAiRun::query()
            ->whereKey($runId)
            ->update([
                'user_id' => $userId,
                'study_practice_session_id' => $session->id,
            ]);
    }

    private function callOpenAi(
        string $prompt,
        array $schema,
        string $schemaName,
        string $model,
        ?int $maxOutputTokens,
    ): array {
        $baseUrl = rtrim((string) config('native_ai.providers.openai.base_url'), '/');
        $apiKey = (string) config('native_ai.providers.openai.api_key');
        $timeout = max(5, min(120, (int) config('native_ai.timeout_seconds', 45)));

        $payload = [
            'model' => $model,
            'input' => $prompt,
            'store' => false,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        if ($maxOutputTokens !== null && $maxOutputTokens > 0) {
            $payload['max_output_tokens'] = $maxOutputTokens;
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout($timeout)
            ->post($baseUrl.'/responses', $payload);

        if (! $response->successful()) {
            $status = $response->status();
            $providerCode = trim((string) data_get($response->json(), 'error.code', ''));
            $message = match (true) {
                $status === 401 || $status === 403 => 'Native AIのAPI認証設定を確認してください。',
                $status === 429 => 'Native AIの利用枠が混雑または上限に達しています。',
                $status >= 500 => 'Native AI providerが一時的に利用できません。',
                default => 'Native AI providerがリクエストを受け付けませんでした。',
            };

            throw new NativeAiExecutionException(
                $message,
                $providerCode !== '' ? 'openai_'.$providerCode : 'openai_http_'.$status,
            );
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new NativeAiExecutionException(
                'Native AI providerの応答を読み取れませんでした。',
                'native_ai_invalid_response',
            );
        }

        return $body;
    }

    private function extractOutputText(array $body): string
    {
        if (is_string($body['output_text'] ?? null)) {
            return trim((string) $body['output_text']);
        }

        $parts = [];
        foreach (($body['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (
                    is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)
                ) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function extractRefusal(array $body): string
    {
        foreach (($body['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (
                    is_array($content)
                    && ($content['type'] ?? null) === 'refusal'
                    && is_string($content['refusal'] ?? null)
                ) {
                    return trim((string) $content['refusal']);
                }
            }
        }

        return '';
    }

    private function markFailed(NativeAiRun $run, string $code, string $message): void
    {
        if ($run->status === 'succeeded') {
            return;
        }

        $run->update([
            'status' => 'failed',
            'error_code' => mb_substr($code, 0, 80),
            'error_message' => mb_substr($message, 0, 4000),
            'completed_at' => now(),
        ]);
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
