# V41 Evidence-first Execution Foundation

## Purpose

V41 changes the center of Canovia execution from:

```text
Task
  -> start timer
  -> work
  -> stop timer
  -> WorkLog
  -> progress
```

to:

```text
Task
  -> Next Action
  -> Execution
  -> Evidence
  -> Progress Decision
  -> Next Action / Plan optimization
```

The Focus Timer remains available, but elapsed time is not treated as proof that a Task progressed.

## Product rule: time is an estimate, not evidence

`estimated_minutes` and `remaining_minutes` remain useful for:

- workload comparison
- calendar capacity
- deadline feasibility
- recommendation sizing
- deciding whether a Task fits the available time

Actual elapsed time may still be recorded by WorkSession, but time alone must not automatically increase `progress_percent`.

UI therefore labels time as **目安** and moves Focus Timer out of the primary execution action.

## Plan Hub

The Home Plan tab is now the day-to-day Plan surface.

It shows:

1. Plan identity, progress, deadline and remaining workload estimate
2. NEXT ACTION / current Task
3. the best specialized execution action when one exists
4. Focus Timer as an optional secondary action
5. the next incomplete Tasks
6. Canovia Tools
7. recent Task Evidence
8. recent WorkLog activity

The large embedded Roadmap is removed from the Plan tab. The dedicated Roadmap page remains the place for route/map-level overview.

The full Plan page remains the management/detail surface.

## Evidence model

### `task_evidences`

Stores an observed fact.

Key fields:

- `plan_id`
- `task_id`
- `user_id` / `actor_token`
- `milestone_id` nullable
- `source`
- `type`
- `provider`
- `provider_reference`
- `summary`
- `confidence` (0-100)
- `observed_duration_seconds` nullable
- `metadata`
- `fingerprint` unique
- `occurred_at`

Evidence is intentionally separate from progress.

Examples:

- AI Practice assessment finished
- GitHub PR merged (future)
- file created/updated (future)
- photo evidence captured and interpreted (future)
- calendar event completed (future)

## Progress Decision model

### `task_progress_decisions`

Stores why a progress value changed or was proposed.

Key fields:

- `task_id`
- `task_evidence_id` nullable
- `source`: rule / ai / user / connector
- `status`: proposed / applied / rejected
- `progress_before_percent`
- `progress_after_percent`
- `reason`
- `metadata`
- `decision_key` unique
- `applied_at`

This lets Canovia separate:

```text
Fact:
  AI Practice score = 90%

Interpretation:
  current weakness is only percentage conversion

Decision:
  Task progress 20 -> 80

Mutation:
  apply after the existing confirmation flow
```

V41 does **not** give arbitrary external evidence permission to rewrite progress.

## Milestone model

### `task_milestones`

A Task may optionally expose concrete completion states.

Fields:

- title
- description
- status
- weight
- sort_order
- completed_at

`TaskMilestoneProgressService` can calculate a weighted completion signal.

Example:

```text
Presentation Task

[x] structure      weight 1
[ ] first draft    weight 3

signal = 25%
```

The signal does not automatically mutate `Task.progress_percent` in V41.

## Execution boundary

`TaskExecutionRegistry` describes how existing tools relate to execution and evidence.

### AI Practice

- execution: native
- evidence: automatic
- primary action eligible: yes

### Focus Timer

- execution: native
- evidence: context only
- primary action eligible: no

Time may be useful context, but it is not Task progress evidence.

### Project Artifact

- execution: connected
- evidence: linked
- primary action eligible: yes when relevant

Today it opens/organizes linked artifacts. Future Drive/GitHub connectors can add automatic Evidence without changing the Task UI contract.

### Resources

- execution: external
- evidence: none by default
- primary action eligible: yes when relevant

Opening a URL does not prove completion.

## First native Evidence: AI Practice

When an assessment is persisted, Canovia records:

- source: `native`
- type: `study_practice_assessed`
- provider: `study_practice`
- provider reference: StudyPracticeAttempt id
- score
- strengths
- weaknesses
- recommended progress
- next action

The Evidence is idempotent by fingerprint.

When the user applies the assessment to the Task, Canovia also records an applied `TaskProgressDecision` linked to that Evidence.

Existing safety remains:

- progress never decreases through AI Practice apply
- Task mutation still requires the existing apply flow
- repeated assessment/apply requests remain idempotent

## Future source vocabulary

V41 reserves the following source families:

### native

Canovia can observe the full workflow.

Examples:
- AI Practice
- future native creation/review tools

### github

Future connector examples:
- commit
- branch
- pull request
- merge
- issue close

### file

Future connector or desktop examples:
- file created
- file updated
- final export created

### image

Future physical-world bridge:
- photo of workbook pages
- handwritten exercise result
- physical prototype state

Image Evidence should retain confidence and AI interpretation separately from the raw captured fact.

### calendar

Future observable life/work events:
- interview scheduled
- appointment started/ended
- presentation or deadline event

A calendar event ending is weaker Evidence than a native completion or merge and should not automatically mean the Task is complete.

## Future AI policy

AI should consume Evidence and propose interpretation, rather than invent facts.

Recommended automation levels:

```text
deterministic fact + deterministic rule
  -> may auto-apply

high-confidence evidence + bounded rule
  -> apply or lightweight notice

ambiguous interpretation
  -> proposal / confirmation

large Plan restructuring
  -> user confirmation
```

The intended future loop is:

```text
Observe
  -> Evidence
  -> AI interpretation
  -> Milestone / Progress proposal
  -> Plan impact analysis
  -> Next Action / optimization
```

## Non-goals of V41

V41 does not yet implement:

- GitHub OAuth/webhook ingestion
- Drive/OneDrive/Dropbox file watchers
- local filesystem watcher
- image upload + vision interpretation
- calendar/mail connector ingestion
- autonomous AI progress rewriting
- autonomous Plan restructuring
- a replacement editor for VS Code, PowerPoint, Word, etc.

The purpose of V41 is to create a stable execution/evidence boundary so those integrations can be added without redesigning Task again.
