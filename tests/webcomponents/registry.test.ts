import { afterEach, beforeEach, expect, test, vi } from 'vite-plus/test';
import { createStore, get, set } from 'idb-keyval';
import { fetchIcon, IconErrorType, refreshCache } from '../../resources/webcomponents/IconRegistry';
import { deferred, iconName, response, svg } from './helpers';

const store = createStore('jooosi-icon', 'icon-cache');
const cacheKey = 'oiwc-cache.key';
const request = (name: string, options = {}) => fetchIcon(name, ...name.split(':') as [string, string], options);
let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
	fetchMock = vi.fn(async () => response());
	vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
	document.head.querySelector('meta[name="jooosi-icon-cache-key"]')?.remove();
	vi.restoreAllMocks();
	vi.unstubAllGlobals();
});

test('clears persisted icons when the page cache key changes', async () => {
	const meta = document.createElement('meta');
	meta.name = 'jooosi-icon-cache-key';
	meta.content = 'cache-key-current';
	document.head.append(meta);
	const name = iconName();
	await set(cacheKey, 'cache-key-old', store);
	await set(`oiwc-cache.${name}`, '<svg>stale</svg>', store);

	expect(await request(name)).toBe(svg);
	expect(fetchMock).toHaveBeenCalledTimes(1);
	expect(await get(cacheKey, store)).toBe('cache-key-current');
	expect(await get(`oiwc-cache.${name}`, store)).toBe(svg);
});

test('refreshCache immediately applies a changed page key', async () => {
	const name = iconName();
	await set(cacheKey, 'cache-key-current', store);
	await set(`oiwc-cache.${name}`, '<svg>stale</svg>', store);

	const meta = document.createElement('meta');
	meta.name = 'jooosi-icon-cache-key';
	meta.content = 'cache-key-refreshed';
	document.head.append(meta);
	await refreshCache();

	expect(await get(cacheKey, store)).toBe('cache-key-refreshed');
	expect(await get(`oiwc-cache.${name}`, store)).toBeUndefined();
});

test('shares one database read and fetch for concurrent callers, then serves memory hits', async () => {
	const name = iconName();
	const reads = vi.spyOn(IDBObjectStore.prototype, 'get');
	expect(await Promise.all(Array.from({ length: 20 }, () => request(name)))).toEqual(Array(20).fill(svg));
	expect(reads).toHaveBeenCalledTimes(1);
	expect(fetchMock).toHaveBeenCalledTimes(1);
	expect(await request(name)).toBe(svg);
	expect(reads).toHaveBeenCalledTimes(1);
	expect(fetchMock).toHaveBeenCalledTimes(1);
});

test('shares persistent cache hits without fetching or rewriting them', async () => {
	const name = iconName();
	await set(`oiwc-cache.${name}`, svg, store);
	const reads = vi.spyOn(IDBObjectStore.prototype, 'get');
	const writes = vi.spyOn(IDBObjectStore.prototype, 'put');
	await Promise.all([request(name), request(name)]);
	expect(reads).toHaveBeenCalledTimes(1);
	expect(writes).not.toHaveBeenCalled();
	expect(fetchMock).not.toHaveBeenCalled();
});

test('falls back to the network when IndexedDB is unavailable', async () => {
	vi.spyOn(IDBObjectStore.prototype, 'get').mockImplementation(() => { throw new Error('Unavailable'); });
	expect(await request(iconName())).toBe(svg);
	expect(fetchMock).toHaveBeenCalledTimes(1);
});

test('an already aborted caller does no database or network work', async () => {
	const reads = vi.spyOn(IDBObjectStore.prototype, 'get');
	await expect(request(iconName(), { signal: AbortSignal.abort() })).rejects.toMatchObject({ type: IconErrorType.FETCH_FAILED });
	expect(reads).not.toHaveBeenCalled();
	expect(fetchMock).not.toHaveBeenCalled();
});

test('cancelling during a database lookup prevents a network request', async () => {
	const name = iconName();
	const controller = new AbortController();
	const pending = expect(request(name, { signal: controller.signal })).rejects.toMatchObject({ type: IconErrorType.FETCH_FAILED });
	controller.abort();
	await pending;
	expect(fetchMock).not.toHaveBeenCalled();
	expect(await request(name)).toBe(svg);
});

test('one consumer can cancel without aborting another consumer of the same icon', async () => {
	const result = deferred<Response>();
	fetchMock.mockReturnValue(result.promise);
	const name = iconName();
	const firstController = new AbortController();
	const first = request(name, { signal: firstController.signal });
	const second = request(name);
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
	firstController.abort();
	expect(fetchMock.mock.calls[0][1].signal.aborted).toBe(false);
	result.resolve(response());
	expect(await Promise.all([first, second])).toEqual([svg, svg]);
});

test('an immediate retry survives cleanup of an aborted request and remains shared', async () => {
	const results: ReturnType<typeof deferred<Response>>[] = [];
	fetchMock.mockImplementation((_url, { signal }) => {
		const result = deferred<Response>();
		results.push(result);
		signal.addEventListener('abort', () => result.reject(new DOMException('Aborted', 'AbortError')), { once: true });
		return result.promise;
	});
	const name = iconName();
	const controller = new AbortController();
	const aborted = expect(request(name, { signal: controller.signal })).rejects.toMatchObject({ type: IconErrorType.FETCH_FAILED });
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
	controller.abort();
	const retry = request(name);
	await aborted;
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
	const sharedRetry = request(name);
	results[1].resolve(response());
	expect(await Promise.all([retry, sharedRetry])).toEqual([svg, svg]);
	expect(fetchMock).toHaveBeenCalledTimes(2);
});

test('limits concurrency, promotes queued priorities, and removes cancelled queued work', async () => {
	const responses: ReturnType<typeof deferred<Response>>[] = [];
	fetchMock.mockImplementation(() => {
		const result = deferred<Response>();
		responses.push(result);
		return result.promise;
	});
	const active = Array.from({ length: 16 }, () => request(iconName()));
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(16));
	const low = request(iconName(), { priority: 1 });
	const highName = iconName();
	const high = request(highName);
	const promoted = request(highName, { priority: 10 });
	const controller = new AbortController();
	const cancelled = expect(request(iconName(), { signal: controller.signal })).rejects.toMatchObject({ type: IconErrorType.FETCH_FAILED });
	// A write transaction runs after the pending readonly cache lookups.
	await set('test-queue-barrier', '', store);
	controller.abort();
	await cancelled;
	expect(fetchMock).toHaveBeenCalledTimes(16);
	responses[0].resolve(response());
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(17));
	expect(fetchMock.mock.calls[16][0]).toContain(highName.split(':')[1]);
	responses.slice(1).forEach((result) => result.resolve(response()));
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(18));
	responses[17].resolve(response());
	await Promise.all([...active, low, high, promoted]);
});

test.each([
	[404, {}, IconErrorType.NAME_NOT_FOUND],
	[500, {}, IconErrorType.FETCH_FAILED],
	[200, {}, IconErrorType.INVALID_RESPONSE],
])('preserves API error classification for status %s', async (status, data, type) => {
	fetchMock.mockResolvedValue(new Response(JSON.stringify(data), { status }));
	await expect(request(iconName())).rejects.toMatchObject({ type });
});
