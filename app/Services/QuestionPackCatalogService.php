<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class QuestionPackCatalogService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        $base = resource_path('question_packs');

        if (! is_dir($base)) {
            return collect();
        }

        $items = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || mb_strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
            $key = preg_replace('/\.json$/i', '', $relative) ?: $relative;
            $payload = $this->decodeFile($file->getPathname());
            $pack = is_array($payload['pack'] ?? null) ? $payload['pack'] : [];
            $questions = is_array($payload['questions'] ?? null) ? $payload['questions'] : [];

            $items[] = [
                'key' => $key,
                'path' => $relative,
                'slug' => (string) ($pack['slug'] ?? ''),
                'title' => (string) ($pack['title'] ?? $key),
                'exam_code' => (string) ($pack['exam_code'] ?? ''),
                'subject' => (string) ($pack['subject'] ?? ''),
                'version' => (string) ($pack['version'] ?? ''),
                'question_count' => count($questions),
                'metadata' => is_array($pack['metadata'] ?? null) ? $pack['metadata'] : [],
            ];
        }

        return collect($items)
            ->sortBy(fn (array $item) => [$item['exam_code'], $item['title'], $item['version']])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $key): array
    {
        $item = $this->all()->firstWhere('key', $key);

        if (! $item) {
            throw ValidationException::withMessages([
                'catalog_key' => '指定されたBundled Question Packを確認できません。',
            ]);
        }

        return $this->decodeFile(resource_path('question_packs/'.$item['path']));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFile(string $path): array
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw ValidationException::withMessages([
                'catalog_key' => 'Question Packファイルを読み込めませんでした。',
            ]);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'catalog_key' => 'Question Packファイルが有効なJSONではありません。',
            ]);
        }

        return $decoded;
    }
}
