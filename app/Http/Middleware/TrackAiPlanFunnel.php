<?php

namespace App\Http\Middleware;

use App\Enums\BehaviorEventType;
use App\Models\Plan;
use App\Services\BehaviorEventLogger;
use App\Services\BehaviorIdentityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class TrackAiPlanFunnel
{
    public function __construct(
        private readonly BehaviorIdentityService $identity,
        private readonly BehaviorEventLogger $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $definition = $this->definition($routeName);

        if (! $definition) {
            return $next($request);
        }

        $plan = $request->route('plan');
        $plan = $plan instanceof Plan ? $plan : null;
        $actorToken = $this->identity->resolve($request);
        $baseMetadata = $this->baseMetadata($request);

        if ($definition['attempt'] ?? null) {
            $this->logger->recordSafely(
                $actorToken,
                $definition['attempt'],
                $request,
                $plan,
                metadata: $baseMetadata,
            );
        }

        try {
            /** @var Response $response */
            $response = $next($request);

            if ($definition['success'] ?? null) {
                $record = ($definition['once'] ?? false) ? 'recordOnceSafely' : 'recordSafely';
                $this->logger->{$record}(
                    $actorToken,
                    $definition['success'],
                    $request,
                    $plan,
                    metadata: $baseMetadata,
                    ...(($definition['once'] ?? false) ? ['withinMinutes' => 5] : []),
                );
            }

            return $response;
        } catch (Throwable $exception) {
            if ($definition['failure'] ?? null) {
                $this->logger->recordSafely(
                    $actorToken,
                    $definition['failure'],
                    $request,
                    $plan,
                    metadata: array_merge($baseMetadata, $this->failureMetadata($exception)),
                );
            }

            throw $exception;
        }
    }

    private function definition(?string $routeName): ?array
    {
        return match ($routeName) {
            'plans.ai_task_assistant.show' => [
                'success' => BehaviorEventType::PlanGenerationOpened,
                'once' => true,
            ],
            'plans.ai_task_assistant.import' => [
                'attempt' => BehaviorEventType::PlanGenerationImportAttempted,
                'success' => BehaviorEventType::PlanGenerationImportSucceeded,
                'failure' => BehaviorEventType::PlanGenerationImportFailed,
            ],
            'plans.review_assistant.show' => [
                'success' => BehaviorEventType::PlanUpdateOpened,
                'once' => true,
            ],
            'plans.review_assistant.prompt' => [
                'success' => BehaviorEventType::PlanUpdatePromptGenerated,
                'failure' => BehaviorEventType::PlanUpdatePreviewFailed,
            ],
            'plans.review_assistant.preview' => [
                'attempt' => BehaviorEventType::PlanUpdatePreviewAttempted,
                'success' => BehaviorEventType::PlanUpdatePreviewSucceeded,
                'failure' => BehaviorEventType::PlanUpdatePreviewFailed,
            ],
            'plans.review_assistant.apply' => [
                'attempt' => BehaviorEventType::PlanUpdateApplyAttempted,
                'success' => BehaviorEventType::PlanUpdateApplied,
                'failure' => BehaviorEventType::PlanUpdateApplyFailed,
            ],
            default => null,
        };
    }

    private function baseMetadata(Request $request): array
    {
        $surface = (string) $request->input('_client_surface', '');
        if (! in_array($surface, ['web', 'pwa'], true)) {
            $surface = 'unknown';
        }

        $ua = (string) $request->userAgent();
        $device = preg_match('/iPhone|iPad|iPod|Android|Mobile/i', $ua) ? 'mobile' : 'desktop';

        return [
            'surface' => $surface,
            'device' => $device,
            'route_name' => (string) ($request->route()?->getName() ?? ''),
        ];
    }

    private function failureMetadata(Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            $errors = $exception->errors();
            $fields = array_values(array_slice(array_keys($errors), 0, 8));
            $firstMessage = collect($errors)->flatten()->first();

            return [
                'failure_code' => $this->classifyValidationFailure($fields, is_string($firstMessage) ? $firstMessage : ''),
                'validation_fields' => $fields,
            ];
        }

        if ($exception instanceof HttpExceptionInterface) {
            return [
                'failure_code' => 'http_'.$exception->getStatusCode(),
                'error_class' => class_basename($exception),
            ];
        }

        return [
            'failure_code' => 'server_error',
            'error_class' => class_basename($exception),
        ];
    }

    private function classifyValidationFailure(array $fields, string $message): string
    {
        $field = $fields[0] ?? 'unknown';

        return match (true) {
            str_contains($message, '別の計画') || str_contains($message, 'target_plan') => 'wrong_plan',
            str_contains($message, 'JSON') || str_contains($message, '構文') || str_contains($message, '閉じ括弧') => 'invalid_json',
            str_contains($message, 'client_ref') || str_contains($message, 'reorder_tasks') => 'invalid_task_references',
            str_contains($message, 'priority') || str_contains($message, 'activation_cost') => 'invalid_task_scale',
            str_contains($message, 'operations') || str_contains($message, '操作') => 'invalid_operations',
            str_contains($message, 'deadline') || str_contains($message, '期限') => 'invalid_deadline',
            str_contains($message, 'タスク') => 'invalid_task',
            default => 'validation_'.$field,
        };
    }
}
