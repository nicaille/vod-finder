const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/search.blade.php'), 'utf8');

function cacheHarness() {
    let timestamp = Date.parse('2026-10-06T10:00:00Z');
    const entries = new Map();
    const sessionStorage = {
        get length() { return entries.size; },
        key: index => [...entries.keys()][index],
        getItem: key => entries.get(key) ?? null,
        setItem: (key, value) => entries.set(key, value),
        removeItem: key => entries.delete(key),
    };
    const context = vm.createContext({ sessionStorage, Date: { now: () => timestamp } });
    const constants = ['STORAGE_RESULTS_PREFIX', 'CACHE_TTL_DAYS', 'CACHE_MAX_ENTRIES']
        .map(name => source.match(new RegExp(`const ${name} = [^;]+;`))[0]).join('\n');
    const functions = ['nowTs', 'daysToMs', 'parseTs', 'listSessionKeysWithPrefix',
        'purgeOldSearchCaches', 'getCachedResultsByKey', 'setCachedResultsByKey'].map(name => {
        const start = source.indexOf(`    function ${name}(`);
        const end = source.indexOf('\n    function ', start + 1);
        return source.slice(start, end);
    }).join('\n');
    vm.runInContext(constants + '\n' + functions, context);
    return { context, entries, advance: ms => { timestamp += ms; } };
}

test('an open tab discards a search at 24 hours, without requiring a page reload', () => {
    const { context, entries, advance } = cacheHarness();
    context.setCachedResultsByKey('dune', { results: [{ id: 1 }] });
    advance(24 * 3600 * 1000 - 1);
    assert.equal(context.getCachedResultsByKey('dune').results[0].id, 1);
    advance(1);
    assert.equal(context.getCachedResultsByKey('dune'), null);
    assert.equal(entries.size, 0);
});

test('reading a search does not extend its lifetime', () => {
    const { context, advance } = cacheHarness();
    context.setCachedResultsByKey('dune', { results: [] });
    advance(23 * 3600 * 1000);
    assert.ok(context.getCachedResultsByKey('dune'));
    advance(3600 * 1000);
    assert.equal(context.getCachedResultsByKey('dune'), null);
});

test('old cache versions and stale entries are purged while recent searches remain', () => {
    const { context, entries, advance } = cacheHarness();
    entries.set('vodfinder_results:v4:old', JSON.stringify({ results: [], ts: Date.now() }));
    context.setCachedResultsByKey('expired', { results: [] });
    advance(25 * 3600 * 1000);
    context.setCachedResultsByKey('recent', { results: [] });
    context.purgeOldSearchCaches();
    assert.equal(entries.size, 1);
    assert.ok(context.getCachedResultsByKey('recent'));
});

test('cache eviction preserves the forty most recent searches', () => {
    const { context, entries, advance } = cacheHarness();
    for (let index = 0; index < 41; index++) {
        context.setCachedResultsByKey(String(index), { results: [] });
        advance(1000);
    }
    context.purgeOldSearchCaches();
    assert.equal(entries.size, 40);
    assert.equal(context.getCachedResultsByKey('0'), null);
    assert.ok(context.getCachedResultsByKey('40'));
});
