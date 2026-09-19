import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { normalizeAiJsonText, buildAiJsonRepairPrompt } from '../../resources/js/ai-json.mjs';

const wrapped = normalizeAiJsonText('説明\n```JSON\n{"schema_version":"2.0","operations":[],}\n```\n以上');
assert.equal(wrapped.parsed.schema_version, '2.0');
assert.deepEqual(wrapped.parsed.operations, []);

const comments = normalizeAiJsonText('{"url":"https://example.com/a//b",/* note */"title":"x,}",}');
assert.equal(comments.parsed.url, 'https://example.com/a//b');
assert.equal(comments.parsed.title, 'x,}');


const multiline = normalizeAiJsonText(`{"description":"line1
line2","operations":[]}`);
assert.equal(multiline.parsed.description, `line1
line2`);

const balanced = normalizeAiJsonText('前置き {"text":"a } b { c","operations":[]} 後置き');
assert.equal(balanced.parsed.text, 'a } b { c');

assert.throws(() => normalizeAiJsonText('{"x":1'), /閉じ括弧/);


const appSource = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
assert.doesNotMatch(appSource, /querySelectorAll\('\[data-async-plan-review\]'\)\.forEach/);
assert.match(appSource, /event\.stopImmediatePropagation\(\)/);
assert.match(appSource, /currentRoot\.innerHTML = nextRoot\.innerHTML/);

const prompt = buildAiJsonRepairPrompt('Syntax error', '{"x":}');
assert.match(prompt, /Canoviaのエラー/);
assert.match(prompt, /元のJSON/);
assert.match(prompt, /Syntax error/);
console.log('ai-json-v38: 9/9 PASS');
