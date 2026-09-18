// Import only what we need for lighter bundle
import { createStore, get as getIdb, set as setIdb } from 'idb-keyval';

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
	cleanup: () => void;
	started: boolean;
}

interface InflightEntry {
	iconName: string;
	promise: Promise<string>;
	abortController: AbortController;
	consumers: Set<ConsumerEntry>;
	queueItem: QueueItem;
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
const IDB_DB_NAME = 'jooosi-icon';
const IDB_STORE_NAME = 'icon-cache';
const MAX_CONCURRENT_REQUESTS = 16;
const idbStore = createStore(IDB_DB_NAME, IDB_STORE_NAME);

const iconCache = new Map<string, string>();
const inflightRequests = new Map<string, InflightEntry>();
const requestQueue: QueueItem[] = [];
let activeRequests = 0;

export async function fetchIcon(
	iconName: string,
	prefix: string,
	name: string,
	options?: IconFetchOptions
): Promise<string> {
	const { signal, priority = 0 } = options ?? {};

	const cached = iconCache.get(iconName);
	if (cached) {
		return cached;
	}

	const stored = await readFromIndexedDb(iconName);
	if (stored) {
		iconCache.set(iconName, stored);
		return stored;
	}

	const entry = ensureInflightEntry(iconName, prefix, name, priority);
	attachConsumer(entry, signal);
	return entry.promise;
}

function ensureInflightEntry(iconName: string, prefix: string, name: string, priority: number): InflightEntry {
	let entry = inflightRequests.get(iconName);
	if (!entry) {
		entry = createInflightEntry(iconName, prefix, name, priority);
		inflightRequests.set(iconName, entry);
	} else if (!entry.queueItem.started && priority > entry.queueItem.priority) {
		entry.queueItem.priority = priority;
		sortQueue();
	}

	return entry;
}

function createInflightEntry(iconName: string, prefix: string, name: string, priority: number): InflightEntry {
	const abortController = new AbortController();
	const consumers = new Set<ConsumerEntry>();
	let queueItem!: QueueItem;

	const entry: InflightEntry = {
		iconName,
		abortController,
		consumers,
		queueItem: {} as QueueItem,
		promise: Promise.resolve(''),
	};

	const basePromise = new Promise<string>((resolve, reject) => {
		queueItem = {
			iconName,
			prefix,
			name,
			priority,
			resolve,
			reject,
			abortController,
			cleanup: () => detachAllConsumers(entry),
			started: false,
		};

		entry.queueItem = queueItem;
		insertIntoQueue(queueItem);
		processQueue();
	});

	entry.promise = basePromise
		.then((svg) => {
			iconCache.set(iconName, svg);
			saveToIndexedDb(iconName, svg);
			return svg;
		})
		.finally(() => {
			detachAllConsumers(entry);
			inflightRequests.delete(iconName);
		});

	return entry;
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

	if (!entry.queueItem.started) {
		removeFromQueue(entry.queueItem);
		entry.queueItem.cleanup();
		entry.queueItem.reject(
			new IconError(
				IconErrorType.FETCH_FAILED,
				`Icon request aborted before start for "${entry.iconName}"`
			)
		);
		return;
	}

	entry.abortController.abort();
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
				// Decrement first to update the queue state before cleanup
				activeRequests--;
				// Cleanup consumers after decrementing to avoid triggering new requests mid-cleanup
				item.cleanup();
				// Process next items in queue
				processQueue();
			});
	}
}

async function processQueueItem(item: QueueItem): Promise<void> {
	try {
		const svg = await performFetch(item.iconName, item.prefix, item.name, item.abortController);
		item.resolve(svg);
	} catch (error) {
		if (error instanceof DOMException && error.name === 'AbortError') {
			item.reject(
				new IconError(
					IconErrorType.FETCH_FAILED,
					`Icon request aborted for "${item.iconName}"`
				)
			);
			return;
		}

		item.reject(error instanceof Error ? error : new Error('Unknown error'));
	}
}

async function readFromIndexedDb(iconName: string): Promise<string | undefined> {
	try {
		const stored = await getIdb(`${IDB_KEY}.${iconName}`, idbStore);
		return typeof stored === 'string' ? stored : undefined;
	} catch {
		// Silent fail - fallback to network fetch
		return undefined;
	}
}

async function saveToIndexedDb(iconName: string, svg: string): Promise<void> {
	try {
		await setIdb(`${IDB_KEY}.${iconName}`, svg, idbStore);
	} catch {
		// Silent fail - icon will be fetched from network next time
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
