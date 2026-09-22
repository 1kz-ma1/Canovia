# V40.0 Learning Core

## Goal

V40 separates five responsibilities that were previously coupled inside AI Practice.

1. **Question source** — where the problem came from
2. **Response schema** — how the learner should answer
3. **Grading rule** — what can be graded deterministically
4. **Learning analysis** — what the learner understood or misunderstood
5. **Plan priority** — which goal should receive attention first

This keeps Canovia useful with the current copy/paste AI flow while preparing for curated Question Packs and future embedded AI.

---

## AI Practice response_fields

A question may now contain one to four response fields.

Supported field types:

- `single_choice`
- `multiple_choice`
- `number`
- `short_text`
- `textarea`

Example:

```json
{
  "id": "q1",
  "prompt": "Choose the answer and explain why.",
  "response_fields": [
    {
      "id": "answer",
      "type": "single_choice",
      "label": "回答",
      "required": true,
      "choices": [
        {"id": "A", "label": "A"},
        {"id": "B", "label": "B"}
      ]
    },
    {
      "id": "reasoning",
      "type": "textarea",
      "label": "考え方・判断理由",
      "required": false
    }
  ]
}
```

The AI chooses from Canovia-defined components. It cannot invent arbitrary HTML or UI controls.

### Backward compatibility

V39 questions using `type + choices` are converted to a single `response_fields.answer` field.

In-progress browser sessions without `response_fields` are normalized when the Study Practice page is reopened.

---

## Per-question learning feedback

`study_assessment` may include `question_feedback`.

Each item can contain:

- `question_id`
- `correctness`: `correct | partial | incorrect | ungraded`
- `feedback`
- `reasoning_feedback`
- `misconceptions[]`

This remains inside the StudyPracticeAttempt assessment JSON, so no separate migration is required.

The aggregate fields remain compatible:

- score_percent
- strengths
- weaknesses
- recommended_task_progress_percent
- evidence_summary
- next_action

---

## Plan Priority: Auto and Manual

Plans now have `priority_mode`.

### Auto

Default for all existing and newly created Plans.

Canovia evaluates objective Plan state using:

- deadline proximity
- expected vs actual progress
- remaining work
- configured available capacity
- schedule/capacity shortage
- recent Plan inactivity

Short-term behavioral readiness does **not** change Plan importance. Readiness remains the responsibility of RecommendationService.

### Manual

The user can lock priority from 1 to 5.

Manual priority always overrides the automatic result until the user switches back to Auto.

### Boundary

- PlanPriorityService: **what goal is important**
- DashboardGuidanceService: **which Task in that Plan is next**
- RecommendationService: **how to approach that selected Task right now**

---

## Resource Tool count

Task resources are PlanResource records linked through `plan_resource_task`, so they are already included in `Plan.resources`.

V39 added both counts and could show five Plan resources plus two Task links as seven.

V40 reports them separately:

- Task related: 2
- Plan total: 5

No duplicate total is produced.

---

## Question Bank foundation

V40 adds:

### question_packs

Stores a distributable unit such as:

- AP / 科目A
- FE / 科目A
- Bookkeeping Level 3

Important fields:

- slug
- exam_code
- subject
- version
- status
- downloadable
- metadata

### questions

Stores:

- source_type
- source_reference
- prompt
- response_schema
- grading_rule
- learning_metadata
- explanation
- difficulty
- sort_order

The Question Bank is intentionally not yet connected to Study Practice selection in V40.

That separation lets the next phase add AP content without rewriting the answer UI.

---

## Source strategy

Future Question Packs should distinguish provenance, for example:

- official
- licensed
- canovia_original
- derived
- ai_generated

The source and licensing conditions must be reviewed per qualification before content is distributed.

---

## Deferred after V40

Not included in this release:

- importing the actual AP question corpus
- Question Pack browsing/download UI
- iOS offline pack synchronization
- qualification support request queue
- Premium entitlement/payment gating
- deterministic grading engine
- validator AI / dual-model verification
- embedded model API

These should be added on top of the V40 domains rather than mixed into this migration.

---

## Regression checks before merge

- `php artisan migrate`
- `php artisan test --filter=LearningCoreV400Test`
- `php artisan test --filter=DashboardGuidanceV395Test`
- `php artisan test --filter=StudyPracticeToolV392Test`
- `php artisan test --filter=StudyPracticeLearningLoopV393Test`
- `php artisan test --filter=AiJsonInputNormalizerV38Test`
- `npm run build`
