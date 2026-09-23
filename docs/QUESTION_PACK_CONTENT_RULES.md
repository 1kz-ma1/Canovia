# Question Pack Content Quality Rules

## Purpose

Canovia Question Bank should improve exam readiness, not merely produce questions that are logically solvable.

A derived or original practice question must therefore preserve the **natural context, terminology, and reading patterns of the target examination**.

These rules apply to:

- bundled Question Packs
- Admin-imported Canovia content
- AI-generated fallback practice
- future embedded-AI question generation

## 1. Preserve semantic context

When a question is derived from a past-question pattern, keep the original meaning domain unless changing it has a clear pedagogical purpose.

Good:

- 病気の有病率
- 検査の陽性率
- 偽陽性率
- メール配送
- TCP通信
- 商品在庫
- 障害復旧

Avoid:

- 病気 → 「異常な対象」
- 人・患者 → 「対象物」
- メール配送 → 「情報の移動」
- サーバ障害 → 「システムの異常事象」

The learner should spend cognitive effort on the tested concept, not on decoding artificial wording.

## 2. Preserve examination terminology

Prefer terms normally used in the target exam and syllabus.

Examples:

- 陽性 / 陰性
- MTBF / MTTR
- ターンアラウンドタイム
- スタベーション
- ビュー
- 正規化
- スループット
- ボトルネック

Do not replace a standard technical term with a vague synonym merely to make a question look different.

## 3. Change only what creates a useful variant

Useful changes include:

- numerical values
- record names
- host names
- processing times
- network sizes
- order quantities
- small structural variations that test the same concept

Avoid changing the surrounding story when that change does not improve the learning objective.

## 4. Preserve the tested decision

A derived question should continue testing the same core judgment.

Examples:

- Bayesian posterior probability remains Bayesian posterior probability.
- MTU/MSS exercises remain about header overhead and fragmentation constraints.
- A view-update question remains about updateability conditions.
- A quality-characteristic question should still require distinguishing the characteristics, not vocabulary guessing.

## 5. Distractors must be educational

Wrong choices should correspond to realistic misconceptions.

Prefer:

- forgetting a header size
- confusing availability with reliability
- confusing CNAME with MX
- confusing document and wide-column NoSQL

Avoid:

- obviously unrelated terms
- joke answers
- wording tricks unrelated to the syllabus

## 6. Do not introduce ambiguity through paraphrasing

A rewritten question must still contain all conditions required for one defensible answer.

Check:

- units
- rounding
- assumptions
- independence
- scheduling policy
- whether preemption is allowed
- whether failure events are independent
- maximum / minimum / appropriate / inappropriate wording

## 7. Keep provenance honest

For `derived` questions:

- keep `source_reference`
- state that the item is a Canovia-original derivative
- never present rewritten text as verbatim official text

For `canovia_original` questions:

- do not invent an official question reference

## 8. Human review checklist

Before publishing a Pack, verify:

1. Is the domain context natural?
2. Would this wording look plausible in the target exam?
3. Did paraphrasing change the concept being tested?
4. Is there exactly one intended correct answer?
5. Are distractors tied to real misconceptions?
6. Are units, conditions, and rounding explicit?
7. Is the explanation consistent with the grading rule?
8. Does learning_metadata describe the actual concept?
9. Is source_type/source_reference accurate?
10. Could the learner practice the intended skill without unnecessary interpretation?

## 9. Example: Bayesian screening question

Preferred:

> ある病気の有病率は2%である。病気に罹患している人を検査すると90%の確率で陽性となり、罹患していない人でも5%の確率で陽性となる。陽性だった人が実際に病気に罹患している確率を求める。

Avoid:

> ある検査対象が異常である確率は2%である。異常な対象を検査すると…

Both may share the same mathematics, but the second adds artificial abstraction that is unrelated to Bayesian reasoning.

## Version

Content rules version: `1.1`

V40.3.1 applies this rule set to the expanded AP Subject A Canovia Core Pack and the external-AI generation prompt.
