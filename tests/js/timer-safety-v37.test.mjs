import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const offline = readFileSync(new URL('../../public/offline.html', import.meta.url), 'utf8');
const today = readFileSync(new URL('../../resources/views/navigation/index.blade.php', import.meta.url), 'utf8');

test('online timer has away-review and long-duration safeguards', () => {
    assert.match(app, /CANOVIA_TIMER_AWAY_THRESHOLD_MS = 15 \* 60 \* 1000/);
    assert.match(app, /data-timer-away-action/);
    assert.match(app, /duration-confirmed/);
    assert.match(app, /pagehide/);
    assert.match(app, /persisted heartbeat before writing a new one/);
});

test('offline shell persists away state and adjustment metadata', () => {
    assert.match(offline, /AWAY_MS=15\*60\*1000/);
    assert.match(offline, /away_started_at/);
    assert.match(offline, /last_heartbeat_at/);
    assert.match(offline, /adjustment_reason/);
    assert.match(offline, /manual_minutes/);
});

test('Today no longer shows a fake pre-start timer and excludes the primary recommendation from alternatives', () => {
    assert.doesNotMatch(today, /text-4xl[^>]*>00:00/);
    assert.match(today, /準備ができたら開始/);
    assert.match(today, /reject\(fn \(\$candidate\).*recommendation->task->id/);
});
