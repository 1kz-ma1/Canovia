const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = readFileSync(require('node:path').join(__dirname, '../public/sw.js'), 'utf8');
async function scenario({ cold = false, retry = '', preload = false, reject = false, timeout = false, method = 'GET' } = {}) {
  const handlers = {}, timers = [];
  let resolveNetwork, rejectNetwork, fetches = 0, result;
  const network = new Promise((resolve, reject) => { resolveNetwork = resolve; rejectNetwork = reject; });
  const shell = new Response('offline-shell');
  const context = {
    URL, Response, Date, Promise, Number, Error,
    setTimeout: (fn, ms) => timers.push({fn, ms}),
    caches: {
      match: async () => shell,
      open: async () => ({ match: async () => new Response(String(cold ? 0 : Date.now())), put: async () => {} })
    },
    fetch: () => { fetches++; return network; },
    self: { location: { origin: 'https://test.invalid' }, addEventListener: (name, fn) => handlers[name] = fn }
  };
  vm.runInNewContext(source, context);
  let responsePromise;
  handlers.fetch({
    request: { method, mode: 'navigate', url: 'https://test.invalid/navigate' + retry },
    preloadResponse: preload ? network : Promise.resolve(undefined),
    waitUntil: () => {}, respondWith: value => { responsePromise = value; value.then(v => result = v); }
  });
  const flush = async () => { for (let i = 0; i < 30; i++) await Promise.resolve(); };
  await flush();
  if (method !== 'GET') { assert.equal(responsePromise, undefined); return; }
  if (cold && !retry) assert.equal(result, shell);
  else {
    for (const timer of timers) if (timer.ms <= (timeout ? 9000 : 2000)) timer.fn();
    await flush();
    if (timeout && !retry) assert.equal(result, shell);
    else assert.equal(result, undefined, '2-second warm response must not be replaced by shell');
  }
  if (reject) rejectNetwork(new Error('offline'));
  else resolveNetwork(new Response('server-page'));
  const response = await responsePromise;
  assert.equal(await response.text(), reject || (cold && !retry) || (timeout && !retry) ? 'offline-shell' : 'server-page');
  assert.equal(fetches, preload ? 0 : 1);
}
(async () => {
  for (const options of [{}, {preload:true}, {cold:true}, {reject:true}, {timeout:true},
    {cold:true,retry:'?_canovia_network=1'}, {cold:true,retry:'?_pk_network=1'}, {method:'POST'}]) await scenario(options);
  console.log('PASS: 8 service-worker cases (slow warm, preload, cold, offline, timeout, both retry keys, POST bypass)');
})().catch(error => { console.error(error); process.exitCode = 1; });
