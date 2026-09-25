// Import only what we need for lighter bundle
import { clear as clearIdb, createStore, get as getIdb, set as setIdb } from 'idb-keyval';

export interface IconApiResponse {
	svg: string;
	prefix?: string;
	name?: string;
}

export enum IconErrorType {
	NO_NAME = 'NO_NAME',
	NAME_NOT_FOUND = 'NAME_NOT_FOUND',
	INVALID_FORMAT = 'INVALID_FORMAT',
	FETCH_FAILED = 'FETCH_FAILED',
	INVALID_RESPONSE = 'INVALID_RESPONSE',
}

export class IconError extends Error {
	constructor(
		public type: IconErrorType,
		message: string,
		public originalError?: Error
	) {
		super(message);
	}
}

interface ConsumerEntry {
	signal: AbortSignal | null;
	handler?: () => void;
}

interface QueueItem {
	iconName: string;
	prefix: string;
	name: string;
	priority: number;
	resolve: (svg: string) => void;
	reject: (error: Error) => void;
	abortController: AbortController;
	started: boolean;
}

interface InflightEntry {
	iconName: string;
	promise: Promise<string>;
	abortController: AbortController;
	consumers: Set<ConsumerEntry>;
	priority: number;
	queueItem: QueueItem | null;
}

export interface IconRegistryStats {
	memorySize: number;
	inflightCount: number;
	queueSize: number;
}

export interface IconFetchOptions {
	signal?: AbortSignal;
	priority?: number;
}

const API_BASE_PATH = '/wp-json/jooosi-icon/v1/icon/item';
const IDB_KEY = 'oiwc-cache';
const IDB_CACHE_KEY = `${IDB_KEY}.key`;
const IDB_DB_NAME = 'jooosi-icon';
const IDB_STORE_NAME = 'icon-cache';
const MAX_CONCURRENT_REQUESTS = 16;
const idbStore = createStore(IDB_DB_NAME, IDB_STORE_NAME);

const iconCache = new Map<string, string>();
const inflightRequests = new Map<string, InflightEntry>();
const requestQueue: QueueItem[] = [];
let activeRequests = 0;
let pageCacheKey: string | null = null;
let cacheKeyPromise: Promise<boolean> | null = null;
let persistentCacheReady = true;

export async function fetchIcon(
	iconName: string,
	prefix: string,
	name: string,
	options?: IconFetchOptions
): Promise<string> {
	const { signal, priority = 0 } = options ?? {};
	if (signal?.aborted) throw abortedRequest(iconName);
	await ensureCacheKey();
	if (signal?.aborted) throw abortedRequest(iconName);

	const cached = iconCache.get(iconName);
	if (cached) {
		return cached;
	}

	const entry = ensureInflightEntry(iconName, prefix, name, priority);
	attachConsumer(entry, signal);
	return entry.promise;
}

/** Synchronize browser storage after a user requests a cache refresh. */
export async function refreshCache(): Promise<void> {
	await ensureCacheKey();
}

function ensureInflightEntry(iconName: string, prefix: string, name: string, priority: number): InflightEntry {
	let entry = inflightRequests.get(iconName);
	if (!entry || entry.abortController.signal.aborted) {
		entry = createInflightEntry(iconName, prefix, name, priority);
		inflightRequests.set(iconName, entry);
	} else if (!entry.queueItem?.started && priority > entry.priority) {
		entry.priority = priority;
		if (entry.queueItem) {
			entry.queueItem.priority = priority;
			sortQueue();
		}
	}

	return entry;
}

function createInflightEntry(iconName: string, prefix: string, name: string, priority: number): InflightEntry {
	const abortController = new AbortController();

	// Share the database lookup as well as the queued network request.
	const promise = readFromIndexedDb(iconName)
		.then((stored) => {
			if (abortController.signal.aborted) throw abortedRequest(iconName);
			if (stored) return stored;

			return new Promise<string>((resolve, reject) => {
				entry.queueItem = {
					iconName,
					prefix,
					name,
					priority: entry.priority,
					resolve,
					reject,
					abortController,
					started: false,
				};
				insertIntoQueue(entry.queueItem);
				processQueue();
			});
		})
		.then((svg) => {
			iconCache.set(iconName, svg);
			return svg;
		})
		.finally(() => {
			detachAllConsumers(entry);
			// An aborted request may already have been replaced by a new caller.
			if (inflightRequests.get(iconName) === entry) {
				inflightRequests.delete(iconName);
			}
		});

	const entry: InflightEntry = {
		iconName,
		promise,
		abortController,
		priority,
		consumers: new Set(),
		queueItem: null,
	};
	return entry;
}

function abortedRequest(iconName: string): IconError {
	return new IconError(IconErrorType.FETCH_FAILED, `Icon request aborted for "${iconName}"`);
}

function attachConsumer(entry: InflightEntry, signal?: AbortSignal): void {
	const consumer: ConsumerEntry = {
		signal: signal ?? null,
	};

	entry.consumers.add(consumer);

	if (signal) {
		const handler = () => handleConsumerAbort(entry, consumer);
		consumer.handler = handler;
		signal.addEventListener('abort', handler);
		if (signal.aborted) {
			handler();
		}
	}
}

function handleConsumerAbort(entry: InflightEntry, consumer: ConsumerEntry): void {
	if (!entry.consumers.has(consumer)) {
		return;
	}

	if (consumer.signal && consumer.handler) {
		consumer.signal.removeEventListener('abort', consumer.handler);
	}

	entry.consumers.delete(consumer);

	if (entry.consumers.size > 0) {
		return;
	}

	entry.abortController.abort();
	if (entry.queueItem && !entry.queueItem.started) {
		removeFromQueue(entry.queueItem);
		entry.queueItem.reject(abortedRequest(entry.iconName));
	}
}

function detachAllConsumers(entry: InflightEntry): void {
	entry.consumers.forEach((consumer) => {
		if (consumer.signal && consumer.handler) {
			consumer.signal.removeEventListener('abort', consumer.handler);
		}
	});
	entry.consumers.clear();
}

function insertIntoQueue(item: QueueItem): void {
	requestQueue.push(item);
	sortQueue();
}

function sortQueue(): void {
	requestQueue.sort((a, b) => b.priority - a.priority);
}

function removeFromQueue(item: QueueItem): void {
	const index = requestQueue.indexOf(item);
	if (index !== -1) {
		requestQueue.splice(index, 1);
	}
}

function processQueue(): void {
	while (activeRequests < MAX_CONCURRENT_REQUESTS && requestQueue.length > 0) {
		const item = requestQueue.shift();
		if (!item) {
			break;
		}

		item.started = true;
		activeRequests++;

		processQueueItem(item)
			.finally(() => {
				activeRequests--;
				processQueue();
			});
	}
}

async function processQueueItem(item: QueueItem): Promise<void> {
	try {
		const svg = await performFetch(item.iconName, item.prefix, item.name, item.abortController);
		saveToIndexedDb(item.iconName, svg);
		item.resolve(svg);
	} catch (error) {
		if (error instanceof DOMException && error.name === 'AbortError') {
			item.reject(abortedRequest(item.iconName));
			return;
		}

		item.reject(error instanceof Error ? error : new Error('Unknown error'));
	}
}

async function readFromIndexedDb(iconName: string): Promise<string | undefined> {
	if (!persistentCacheReady) return undefined;

	try {
		const stored = await getIdb(`${IDB_KEY}.${iconName}`, idbStore);
		return typeof stored === 'string' ? stored : undefined;
	} catch {
		// Silent fail - fallback to network fetch
		return undefined;
	}
}

async function saveToIndexedDb(iconName: string, svg: string): Promise<void> {
	if (!persistentCacheReady) return;

	try {
		await setIdb(`${IDB_KEY}.${iconName}`, svg, idbStore);
	} catch {
		// Silent fail - icon will be fetched from network next time
	}
}

/**
 * Clear persisted icons when the page key changes, before any cache lookup.
 * Icon keys keep their existing format; the random key is stored separately.
 */
function ensureCacheKey(): Promise<boolean> {
	const currentKey = typeof document !== 'undefined'
		? document.head?.querySelector<HTMLMetaElement>('meta[name="jooosi-icon-cache-key"]')?.content
		: undefined;
	if (typeof currentKey !== 'string' || currentKey.length === 0) {
		return Promise.resolve(true);
	}

	if (currentKey === pageCacheKey && cacheKeyPromise) {
		return cacheKeyPromise;
	}

	pageCacheKey = currentKey;
	cacheKeyPromise = syncCacheKey(currentKey);
	return cacheKeyPromise;
}

async function syncCacheKey(currentKey: string): Promise<boolean> {
	try {
		const storedKey = await getIdb(IDB_CACHE_KEY, idbStore);
		if (storedKey !== currentKey) {
			// This object store is dedicated to Jooosi Icon's cached SVGs.
			await clearIdb(idbStore);
			await setIdb(IDB_CACHE_KEY, currentKey, idbStore);
			iconCache.clear();
		}
		persistentCacheReady = true;
		return true;
	} catch {
		// Don't trust persisted icon entries if their key couldn't be checked.
		persistentCacheReady = false;
		return false;
	}
}

async function performFetch(
	iconName: string,
	prefix: string,
	name: string,
	abortController?: AbortController
): Promise<string> {
	try {
		const url = `${API_BASE_PATH}/${encodeURIComponent(prefix)}/${encodeURIComponent(name)}`;
		const response = await fetch(url, {
			headers: { Accept: 'application/json' },
			signal: abortController?.signal,
		});

		if (!response.ok) {
			throw new IconError(
				response.status === 404 ? IconErrorType.NAME_NOT_FOUND : IconErrorType.FETCH_FAILED,
				response.status === 404
					? `We couldn't find an icon with the provided name.`
					: `Failed to fetch icon: ${response.status} ${response.statusText}`
			);
		}

		const data: IconApiResponse = await response.json();

		if (!data.svg) {
			throw new IconError(
				IconErrorType.INVALID_RESPONSE,
				`Invalid API response: missing SVG data for "${iconName}"`
			);
		}

		return data.svg;
	} catch (error) {
		if (error instanceof IconError || (error instanceof DOMException && error.name === 'AbortError')) {
			throw error;
		}
		throw new IconError(
			IconErrorType.FETCH_FAILED,
			`Network error while fetching icon "${iconName}"`,
			error instanceof Error ? error : undefined
		);
	}
}

const IconRegistry = {
	fetchIcon,
	refreshCache,
};

declare global {
	interface Window {
		IconRegistry?: typeof IconRegistry;
	}
}

if (typeof window !== 'undefined') {
	window.IconRegistry = IconRegistry;
}

export default IconRegistry;
