<?php

namespace App\Http\Middleware;

use App\Services\AiJsonInputNormalizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class NormalizeAiJsonInput
{
    /** @var list<string> */
    private const FIELDS = [
        'operations_json',
        'tasks_json',
        'ai_json',
        'assignment_json',
    ];

    public function __construct(private readonly AiJsonInputNormalizer $normalizer)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        foreach (self::FIELDS as $field) {
            $value = $request->input($field);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                $request->merge([$field => $this->normalizer->normalize($value)]);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    $field => $exception->getMessage(),
                ]);
            }
        }

        return $next($request);
    }
}
