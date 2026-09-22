<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionPack;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionPackImportService
{
    /**
     * @param array<string, mixed> $payload
     * @return array{pack:QuestionPack,created:int,updated:int,deactivated:int,total:int}
     */
    public function import(array $payload): array
    {
        if (($payload['schema_version'] ?? null) !== '1.0') {
            $this->fail('schema_version', 'schema_versionは1.0にしてください。');
        }

        $packData = $payload['pack'] ?? null;
        $rawQuestions = $payload['questions'] ?? null;

        if (! is_array($packData)) {
            $this->fail('pack_json', 'packオブジェクトが必要です。');
        }
        if (! is_array($rawQuestions) || $rawQuestions === [] || count($rawQuestions) > 1000) {
            $this->fail('pack_json', 'questionsは1〜1000問で指定してください。');
        }

        $slug = trim((string) ($packData['slug'] ?? ''));
        $title = trim((string) ($packData['title'] ?? ''));
        $examCode = trim((string) ($packData['exam_code'] ?? ''));
        $subject = trim((string) ($packData['subject'] ?? ''));
        $version = trim((string) ($packData['version'] ?? '1'));

        if ($slug === '' || preg_match('/^[a-z0-9][a-z0-9_-]{1,119}$/', $slug) !== 1) {
            $this->fail('pack_json', 'pack.slugは小文字英数字・_・-で2〜120文字にしてください。');
        }
        if ($title === '') {
            $this->fail('pack_json', 'pack.titleが必要です。');
        }
        if ($version === '') {
            $this->fail('pack_json', 'pack.versionが必要です。');
        }

        $metadata = is_array($packData['metadata'] ?? null) ? $packData['metadata'] : [];
        $matchTerms = collect($metadata['match_terms'] ?? [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn ($item) => mb_substr(trim($item), 0, 120))
            ->unique()
            ->take(30)
            ->values()
            ->all();
        $metadata['match_terms'] = $matchTerms;

        $existing = QuestionPack::query()->where('slug', $slug)->first();
        if ($existing && in_array($existing->status, ['published', 'retired'], true)) {
            $this->fail(
                'pack_json',
                '公開済み・終了済みPackはJSONで上書きできません。新しいslug/versionのDraftとして取り込んでください。'
            );
        }

        $normalizedQuestions = [];
        $externalKeys = [];

        foreach (array_values($rawQuestions) as $index => $rawQuestion) {
            $normalized = $this->normalizeQuestion($rawQuestion, $index);

            if (isset($externalKeys[$normalized['external_key']])) {
                $this->fail('pack_json', 'question.external_keyがPack内で重複しています。');
            }

            $externalKeys[$normalized['external_key']] = true;
            $normalizedQuestions[] = $normalized;
        }

        return DB::transaction(function () use (
            $existing,
            $slug,
            $title,
            $examCode,
            $subject,
            $version,
            $metadata,
            $packData,
            $normalizedQuestions,
        ) {
            $pack = $existing ?: new QuestionPack();
            $pack->fill([
                'slug' => $slug,
                'title' => mb_substr($title, 0, 180),
                'exam_code' => $examCode !== '' ? mb_substr($examCode, 0, 80) : null,
                'subject' => $subject !== '' ? mb_substr($subject, 0, 120) : null,
                'version' => mb_substr($version, 0, 40),
                'status' => 'draft',
                'downloadable' => array_key_exists('downloadable', $packData)
                    ? (bool) $packData['downloadable']
                    : true,
                'metadata' => $metadata,
            ]);
            $pack->save();

            $created = 0;
            $updated = 0;

            foreach ($normalizedQuestions as $questionData) {
                $question = Question::query()
                    ->where('question_pack_id', $pack->id)
                    ->where('external_key', $questionData['external_key'])
                    ->first();

                if ($question) {
                    $question->update($questionData);
                    $updated++;
                } else {
                    $pack->questions()->create($questionData);
                    $created++;
                }
            }

            $importedKeys = collect($normalizedQuestions)->pluck('external_key')->all();
            $deactivated = Question::query()
                ->where('question_pack_id', $pack->id)
                ->whereNotIn('external_key', $importedKeys)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return [
                'pack' => $pack->fresh(),
                'created' => $created,
                'updated' => $updated,
                'deactivated' => $deactivated,
                'total' => count($normalizedQuestions),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeQuestion(mixed $raw, int $index): array
    {
        if (! is_array($raw)) {
            $this->fail('pack_json', '各questionはJSONオブジェクトにしてください。');
        }

        $externalKey = trim((string) ($raw['external_key'] ?? 'q'.($index + 1)));
        $sourceType = trim((string) ($raw['source_type'] ?? 'canovia_original'));
        $sourceReference = trim((string) ($raw['source_reference'] ?? ''));
        $prompt = trim((string) ($raw['prompt'] ?? ''));
        $responseSchema = $raw['response_schema'] ?? null;
        $gradingRule = $raw['grading_rule'] ?? null;
        $learningMetadata = is_array($raw['learning_metadata'] ?? null)
            ? $raw['learning_metadata']
            : [];

        if ($externalKey === '' || mb_strlen($externalKey) > 120) {
            $this->fail('pack_json', 'question.external_keyは1〜120文字にしてください。');
        }
        if (! in_array($sourceType, Question::SOURCE_TYPES, true)) {
            $this->fail('pack_json', 'question.source_typeがCanoviaの対応値ではありません。');
        }
        if (
            in_array($sourceType, ['official', 'licensed', 'derived'], true)
            && $sourceReference === ''
        ) {
            $this->fail('pack_json', "{$sourceType}のquestionにはsource_referenceが必要です。");
        }
        if ($prompt === '') {
            $this->fail('pack_json', 'question.promptが空の問題があります。');
        }

        $responseSchema = $this->normalizeResponseSchema($responseSchema);
        $gradingRule = $this->normalizeGradingRule($gradingRule, $responseSchema);

        foreach (['concepts', 'weakness_targets', 'tags', 'keywords'] as $key) {
            $learningMetadata[$key] = collect($learningMetadata[$key] ?? [])
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->map(fn ($item) => mb_substr(trim($item), 0, 120))
                ->unique()
                ->take(30)
                ->values()
                ->all();
        }

        $difficulty = filter_var($raw['difficulty'] ?? 3, FILTER_VALIDATE_INT);
        if ($difficulty === false || $difficulty < 1 || $difficulty > 5) {
            $this->fail('pack_json', 'question.difficultyは1〜5の整数にしてください。');
        }

        $sortOrder = filter_var($raw['sort_order'] ?? ($index + 1), FILTER_VALIDATE_INT);
        if ($sortOrder === false || $sortOrder < 0) {
            $this->fail('pack_json', 'question.sort_orderは0以上の整数にしてください。');
        }

        return [
            'external_key' => mb_substr($externalKey, 0, 120),
            'source_type' => $sourceType,
            'source_reference' => $sourceReference !== ''
                ? mb_substr($sourceReference, 0, 500)
                : null,
            'prompt' => mb_substr($prompt, 0, 12000),
            'response_schema' => $responseSchema,
            'grading_rule' => $gradingRule,
            'learning_metadata' => $learningMetadata,
            'explanation' => filled($raw['explanation'] ?? null)
                ? mb_substr(trim((string) $raw['explanation']), 0, 12000)
                : null,
            'difficulty' => (int) $difficulty,
            'sort_order' => (int) $sortOrder,
            'is_active' => array_key_exists('is_active', $raw)
                ? (bool) $raw['is_active']
                : true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResponseSchema(mixed $raw): array
    {
        if (! is_array($raw) || count($raw) < 1 || count($raw) > 4) {
            $this->fail('pack_json', 'response_schemaは1〜4個の回答フィールドにしてください。');
        }

        $allowedTypes = ['single_choice', 'multiple_choice', 'number', 'short_text', 'textarea'];
        $result = [];
        $seen = [];
        $requiredCount = 0;

        foreach (array_values($raw) as $index => $field) {
            if (! is_array($field)) {
                $this->fail('pack_json', 'response_schemaの各fieldはJSONオブジェクトにしてください。');
            }

            $id = trim((string) ($field['id'] ?? 'field'.($index + 1)));
            $type = trim((string) ($field['type'] ?? ''));
            $label = trim((string) ($field['label'] ?? '回答'));
            $required = array_key_exists('required', $field) ? (bool) $field['required'] : true;

            if (
                $id === ''
                || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1
                || isset($seen[$id])
            ) {
                $this->fail('pack_json', 'response_schema field.idは英数字・_・-の重複しない値にしてください。');
            }
            if (! in_array($type, $allowedTypes, true)) {
                $this->fail('pack_json', 'response_schema field.typeがCanoviaの対応形式ではありません。');
            }

            $choices = [];
            if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
                $rawChoices = $field['choices'] ?? [];
                if (! is_array($rawChoices) || count($rawChoices) < 2 || count($rawChoices) > 8) {
                    $this->fail('pack_json', '選択式fieldのchoicesは2〜8件にしてください。');
                }

                $choiceIds = [];
                foreach (array_values($rawChoices) as $choiceIndex => $choice) {
                    if (! is_array($choice)) {
                        $this->fail('pack_json', 'choiceはidとlabelを持つJSONオブジェクトにしてください。');
                    }

                    $choiceId = trim((string) ($choice['id'] ?? chr(65 + $choiceIndex)));
                    $choiceLabel = trim((string) ($choice['label'] ?? ''));

                    if (
                        $choiceId === ''
                        || preg_match('/^[\p{L}\p{N}_-]{1,20}$/u', $choiceId) !== 1
                        || isset($choiceIds[$choiceId])
                        || $choiceLabel === ''
                    ) {
                        $this->fail('pack_json', 'choice.idは重複しない文字・数字・_・-にし、labelも指定してください。');
                    }

                    $choiceIds[$choiceId] = true;
                    $choices[] = [
                        'id' => $choiceId,
                        'label' => mb_substr($choiceLabel, 0, 1000),
                    ];
                }
            }

            if ($required) {
                $requiredCount++;
            }

            $seen[$id] = true;
            $result[] = [
                'id' => $id,
                'type' => $type,
                'label' => mb_substr($label !== '' ? $label : '回答', 0, 120),
                'required' => $required,
                'placeholder' => mb_substr(trim((string) ($field['placeholder'] ?? '')), 0, 240),
                'choices' => $choices,
            ];
        }

        if ($requiredCount === 0) {
            $this->fail('pack_json', 'response_schemaには最低1つrequired=trueのfieldが必要です。');
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $responseSchema
     * @return array<string, mixed>|null
     */
    private function normalizeGradingRule(mixed $raw, array $responseSchema): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }
        if (! is_array($raw)) {
            $this->fail('pack_json', 'grading_ruleはJSONオブジェクトにしてください。');
        }

        $type = trim((string) ($raw['type'] ?? ''));
        $fieldId = trim((string) ($raw['field_id'] ?? 'answer'));
        $field = collect($responseSchema)->firstWhere('id', $fieldId);

        if (! $field) {
            $this->fail('pack_json', 'grading_rule.field_idがresponse_schemaに存在しません。');
        }

        if ($type === 'exact_choice') {
            if (($field['type'] ?? null) !== 'single_choice') {
                $this->fail('pack_json', 'exact_choiceはsingle_choice fieldにだけ使えます。');
            }
            $answer = (string) ($raw['answer'] ?? '');
            $allowed = collect($field['choices'] ?? [])->pluck('id')->map('strval')->all();
            if (! in_array($answer, $allowed, true)) {
                $this->fail('pack_json', 'exact_choice.answerがchoicesに存在しません。');
            }

            return ['type' => $type, 'field_id' => $fieldId, 'answer' => $answer];
        }

        if ($type === 'exact_multiple') {
            if (($field['type'] ?? null) !== 'multiple_choice') {
                $this->fail('pack_json', 'exact_multipleはmultiple_choice fieldにだけ使えます。');
            }
            $answers = collect($raw['answers'] ?? [])->map('strval')->unique()->values()->all();
            $allowed = collect($field['choices'] ?? [])->pluck('id')->map('strval')->all();
            if ($answers === [] || collect($answers)->contains(fn ($answer) => ! in_array($answer, $allowed, true))) {
                $this->fail('pack_json', 'exact_multiple.answersをchoices内の1件以上で指定してください。');
            }

            return ['type' => $type, 'field_id' => $fieldId, 'answers' => $answers];
        }

        if ($type === 'numeric_tolerance') {
            if (($field['type'] ?? null) !== 'number') {
                $this->fail('pack_json', 'numeric_toleranceはnumber fieldにだけ使えます。');
            }
            if (! is_numeric($raw['answer'] ?? null)) {
                $this->fail('pack_json', 'numeric_tolerance.answerは数値にしてください。');
            }
            if (isset($raw['tolerance']) && ! is_numeric($raw['tolerance'])) {
                $this->fail('pack_json', 'numeric_tolerance.toleranceは数値にしてください。');
            }

            return [
                'type' => $type,
                'field_id' => $fieldId,
                'answer' => (float) $raw['answer'],
                'tolerance' => max(0.0, (float) ($raw['tolerance'] ?? 0)),
            ];
        }

        if ($type === 'ai_rubric') {
            $rubric = trim((string) ($raw['rubric'] ?? ''));
            if ($rubric === '') {
                $this->fail('pack_json', 'ai_rubricにはrubricが必要です。');
            }

            return [
                'type' => $type,
                'field_id' => $fieldId,
                'rubric' => mb_substr($rubric, 0, 6000),
            ];
        }

        $this->fail('pack_json', 'grading_rule.typeがCanoviaの対応形式ではありません。');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
