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

    public function test_reports_structural_smart_quotes_without_rewriting_semantic_content(): void
    {
        try {
            $this->normalizer->normalize('{“schema_version”:“1.0”,“flow”:“study_practice”}');
            $this->fail('Expected invalid smart-quote JSON to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('スマートクォート', $exception->getMessage());
            $this->assertStringContainsString('半角ダブルクォート', $exception->getMessage());
        }

        $json = $this->normalizer->normalize('{"text":"He said “yes”","operations":[]}');
        $this->assertSame('He said “yes”', json_decode($json, true)['text']);
    }

    public function test_throws_actionable_error_for_unclosed_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('閉じ括弧');
        $this->normalizer->normalize('{"schema_version":"2.0"');
    }
}
