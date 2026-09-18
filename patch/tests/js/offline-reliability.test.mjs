import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const timerSource = source.slice(source.indexOf('async function mountOfflineTimerCard('), source.indexOf('async function clearOfflineState('));

function fixture(session, results) {
    const elements = new Map();
    const listeners = new Map();
    const writes = [], submissions = [], removed = [], destinations = [];
    const element = (selector) => {
        if (!elements.has(selector)) elements.set(selector, {
            textContent: '', disabled: false,
            classList: { remove() {}, toggle() {} },
            addEventListener(type, callback) { listeners.set(selector + ':' + type, callback); },
        });
        return elements.get(selector);
    };
    const card = { classList: { remove() {} }, querySelector: element };
    const context = vm.createContext({
        document: { querySelector: () => card }, navigator: { onLine: true },
        window: { addEventListener() {}, setInterval() {}, location: { assign: (url) => destinations.push(url) } },
        formatTimer: String, offlineSessionElapsedSeconds: () => 300,
        writeOfflineState: async (_, value) => writes.push(structuredClone(value)),
        deleteOfflineState: async (key) => removed.push(key), setSyncStatus() {},
        syncOfflineWorkSession: async (value) => { submissions.push(structuredClone(value)); return results.shift(); },
    });
    vm.runInContext(timerSource, context);
    return { context, session, elements, listeners, writes, submissions, removed, destinations };
}

test('a failed completion can retry the same finished record without extending time', async () => {
    const f = fixture({ client_session_id: 'same-id', started_at: new Date(Date.now() - 300000).toISOString() }, [null, { work_session_id: 7 }]);
    await f.context.mountOfflineTimerCard(f.session);
    const complete = f.listeners.get('[data-offline-timer-complete]:click');
    await complete();
    assert.equal(f.elements.get('[data-offline-timer-complete]').textContent, '同期を再試行');
    assert.equal(f.elements.get('[data-offline-timer-toggle]').disabled, true);
    assert.equal(f.removed.length, 0);
    await complete();
    assert.deepEqual(f.submissions[0], f.submissions[1]);
    assert.equal(f.submissions[1].actual_seconds, 300);
    assert.deepEqual(f.destinations, ['/work-sessions/7/review']);
    assert.deepEqual(f.removed, ['offline_session']);
});

test('a finished unsynced session can be mounted after a page reload', async () => {
    const f = fixture({ ended_at: new Date().toISOString(), actual_seconds: 42 }, [null]);
    assert.equal(await f.context.mountOfflineTimerCard(f.session), true);
    assert.equal(f.elements.get('[data-offline-timer-complete]').textContent, '同期を再試行');
    await f.listeners.get('[data-offline-timer-complete]:click')();
    assert.equal(f.submissions[0].actual_seconds, 42);
    assert.equal(f.removed.length, 0);
});

test('a local persistence failure retains the record and prevents a network submission', async () => {
    const f = fixture({ started_at: new Date().toISOString() }, []);
    f.context.writeOfflineState = async () => { throw new Error('quota'); };
    await f.context.mountOfflineTimerCard(f.session);
    await f.listeners.get('[data-offline-timer-complete]:click')();
    assert.equal(f.submissions.length, 0);
    assert.equal(f.removed.length, 0);
    assert.match(f.elements.get('[data-offline-timer-note]').textContent, /保存できません/);
});

test('repeated readiness probes cannot overlap or fetch when offline', async () => {
    const html = readFileSync(new URL('../../public/offline.html', import.meta.url), 'utf8');
    const probeSource = html.slice(html.indexOf('async function probe(){'), html.indexOf("\ndocument.querySelectorAll('[data-shell-view]')"));
    let resolveFetch, calls = 0;
    const context = vm.createContext({
        handoffInProgress: false, probeInFlight: false, serverReady: false,
        navigator: { onLine: true }, AbortController,
        setTimeout: () => 1, clearTimeout() {}, controls() {}, resumeOriginal() {},
        window: { setTimeout() {} },
        document: { getElementById: () => ({ classList: { add() {}, remove() {} } }) },
        fetch: () => { calls++; return new Promise(resolve => { resolveFetch = resolve; }); },
    });
    vm.runInContext(probeSource, context);
    const pending = context.probe();
    await context.probe();
    assert.equal(calls, 1);
    resolveFetch({ ok: true });
    await pending;
    assert.equal(context.probeInFlight, false);
    context.navigator.onLine = false;
    await context.probe();
    assert.equal(calls, 1);
});
