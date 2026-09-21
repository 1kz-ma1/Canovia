<?php

namespace Tests\Unit;

use App\Services\AiJsonInputNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AiJsonInputNormalizerV38Test extends TestCase
{
    private AiJsonInputNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new AiJsonInputNormalizer();
    }

    public function test_extracts_json_from_markdown_and_surrounding_text(): void
    {
        $json = $this->normalizer->normalize("説明です。\n```JSON\n{\"schema_version\":\"2.0\",\"operations\":[]}\n```\n以上です。");
        $this->assertSame('2.0', json_decode($json, true)['schema_version']);
    }

    public function test_repairs_trailing_commas_and_comments_without_changing_values(): void
    {
        $json = $this->normalizer->normalize(<<<'JSON'
{
  "url": "https://example.com/a//b",
  // AI comment
  "title": "keep,}",
  "operations": [
    {"type": "update_task",},
  ],
}
JSON);
        $decoded = json_decode($json, true);
        $this->assertSame('https://example.com/a//b', $decoded['url']);
        $this->assertSame('keep,}', $decoded['title']);
        $this->assertSame('update_task', $decoded['operations'][0]['type']);
    }

    public function test_escapes_literal_newlines_inside_json_strings(): void
    {
        $json = $this->normalizer->normalize("{\"description\":\"line1\nline2\",\"operations\":[]}");
        $this->assertSame("line1\nline2", json_decode($json, true)['description']);
    }

    public function test_balanced_extraction_ignores_braces_inside_strings(): void
    {
        $json = $this->normalizer->normalize('前置き {"text":"a } b { c","operations":[]} 後置き');
        $this->assertSame('a } b { c', json_decode($json, true)['text']);
    }

    public function test_repairs_structural_smart_quotes_without_rewriting_semantic_content(): void
    {
        $json = $this->normalizer->normalize(
            '{“schema_version”:“1.0”,“flow”:“study_practice”,“title”:“いわゆる“ゼロトラスト”とは”}'
        );
        $decoded = json_decode($json, true);

        $this->assertSame('1.0', $decoded['schema_version']);
        $this->assertSame('study_practice', $decoded['flow']);
        $this->assertSame('いわゆる“ゼロトラスト”とは', $decoded['title']);
    }

    public function test_repairs_fullwidth_structural_punctuation_only_outside_strings(): void
    {
        $json = $this->normalizer->normalize(
            '｛"title"："A，B：C"，"items"：［1，2，3］｝'
        );
        $decoded = json_decode($json, true);

        $this->assertSame('A，B：C', $decoded['title']);
        $this->assertSame([1, 2, 3], $decoded['items']);
    }

    public function test_throws_actionable_error_for_unclosed_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Canoviaで安全に自動補正できる範囲を試しました');
        $this->expectExceptionMessage('閉じ括弧');
        $this->normalizer->normalize('{"schema_version":"2.0"');
    }
}
