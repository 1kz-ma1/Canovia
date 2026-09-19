<?php

namespace App\Services;

use InvalidArgumentException;

class AiJsonInputNormalizer
{
    /**
     * Extract the JSON document from an AI response and apply only syntax-safe
     * repairs (comments/trailing commas). Semantic values are never changed.
     */
    public function normalize(string $value): string
    {
        $text = trim($this->stripBom($value));

        if ($text === '') {
            throw new InvalidArgumentException('AIの最後の回答を貼り付けてください。');
        }

        $candidates = $this->fencedCandidates($text);

        try {
            $candidates[] = $this->balancedJsonCandidate($text);
        } catch (InvalidArgumentException $exception) {
            if ($candidates === []) {
                throw $exception;
            }
        }

        $candidates[] = $text;
        $seen = [];
        $lastError = null;

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;

            try {
                $balanced = $this->balancedJsonCandidate($candidate);
                $normalized = trim($this->stripTrailingCommas($this->stripComments($balanced)));
                $normalized = $this->escapeControlCharactersInStrings($normalized);
                $decoded = json_decode($normalized, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $normalized;
                }

                $lastError = json_last_error_msg();
            } catch (InvalidArgumentException $exception) {
                $lastError = $exception->getMessage();
            }
        }

        $detail = $this->humanizeDecodeError($lastError);

        throw new InvalidArgumentException(
            'JSONの構文を読み取れませんでした。' . $detail . '。下の修正依頼をコピーしてAIへ送ってください。'
        );
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /** @return list<string> */
    private function fencedCandidates(string $text): array
    {
        preg_match_all('/```(?:json)?\s*(.*?)\s*```/is', $text, $matches);

        $candidates = array_map(
            fn ($candidate) => trim((string) $candidate),
            $matches[1] ?? []
        );

        return array_values(array_filter($candidates, fn ($candidate) => $candidate !== ''));
    }

    private function balancedJsonCandidate(string $text): string
    {
        $length = strlen($text);
        $start = null;
        $stack = [];
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];

            if ($start === null) {
                if ($char === '{' || $char === '[') {
                    $start = $index;
                    $stack[] = $char === '{' ? '}' : ']';
                }
                continue;
            }

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{' || $char === '[') {
                $stack[] = $char === '{' ? '}' : ']';
                continue;
            }

            if ($char === '}' || $char === ']') {
                $expected = end($stack);
                if ($expected === false || $expected !== $char) {
                    throw new InvalidArgumentException('JSONの括弧の対応が崩れています');
                }

                array_pop($stack);
                if ($stack === []) {
                    return trim(substr($text, $start, $index - $start + 1));
                }
            }
        }

        if ($start === null) {
            throw new InvalidArgumentException('JSONオブジェクト（{ ... }）を見つけられませんでした');
        }
        if ($inString) {
            throw new InvalidArgumentException('JSON内の文字列を閉じる引用符（"）が不足しています');
        }

        throw new InvalidArgumentException('JSONの閉じ括弧が不足しています');
    }

    private function stripComments(string $text): string
    {
        $output = '';
        $length = strlen($text);
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];
            $next = $index + 1 < $length ? $text[$index + 1] : null;

            if ($inString) {
                $output .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                $output .= $char;
                continue;
            }

            if ($char === '/' && $next === '/') {
                $index += 2;
                while ($index < $length && ! in_array($text[$index], ["\n", "\r"], true)) {
                    $index++;
                }
                if ($index < $length) {
                    $output .= $text[$index];
                }
                continue;
            }

            if ($char === '/' && $next === '*') {
                $index += 2;
                while ($index < $length - 1 && ! ($text[$index] === '*' && $text[$index + 1] === '/')) {
                    $index++;
                }
                if ($index < $length - 1) {
                    $index++;
                }
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function stripTrailingCommas(string $text): string
    {
        $output = '';
        $length = strlen($text);
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];

            if ($inString) {
                $output .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                $output .= $char;
                continue;
            }

            if ($char === ',') {
                $cursor = $index + 1;
                while ($cursor < $length && in_array($text[$cursor], [" ", "\t", "\r", "\n"], true)) {
                    $cursor++;
                }
                if ($cursor < $length && in_array($text[$cursor], ['}', ']'], true)) {
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function escapeControlCharactersInStrings(string $text): string
    {
        $output = '';
        $length = strlen($text);
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];

            if (! $inString) {
                $output .= $char;
                if ($char === '"') {
                    $inString = true;
                }
                continue;
            }

            if ($escaped) {
                $output .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $output .= $char;
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $output .= $char;
                $inString = false;
                continue;
            }

            $code = ord($char);
            if ($code < 0x20) {
                $output .= match ($char) {
                    "\n" => '\\n',
                    "\r" => '\\r',
                    "\t" => '\\t',
                    default => sprintf('\\u%04x', $code),
                };
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function humanizeDecodeError(?string $error): string
    {
        $error = trim((string) $error);
        if ($error === '') {
            return '引用符・カンマ・括弧を確認してください';
        }

        return match ($error) {
            'Syntax error' => '引用符・カンマ・括弧のどこかに構文エラーがあります',
            'Control character error, possibly incorrectly encoded' => '文字列内にそのまま使えない改行や制御文字があります',
            'Malformed UTF-8 characters, possibly incorrectly encoded' => 'UTF-8として読み取れない文字が含まれています',
            default => rtrim($error, '。'),
        };
    }
}
