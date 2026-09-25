import { afterEach, beforeEach, expect, test, vi } from 'vite-plus/test';
import '../../resources/webcomponents/jooosi-icon';
import { deferred, iconName, response, tick } from './helpers';

let fixture: HTMLDivElement;
let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
	fixture = document.createElement('div');
	document.body.append(fixture);
	fetchMock = vi.fn(async () => response());
	vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
	fixture.remove();
	vi.restoreAllMocks();
	vi.unstubAllGlobals();
});

function icon(tag = 'jooosi-icon') {
	const element = document.createElement(tag);
	element.setAttribute('name', iconName());
	return element;
}

const event = (element: Element, type = 'loaded') => new Promise<CustomEvent>((resolve) => {
	element.addEventListener(`jooosi-icon:${type}`, (value) => resolve(value as CustomEvent), { once: true });
});

async function mount(element = icon()) {
	const loaded = event(element);
	fixture.append(element);
	await loaded;
	await tick();
	return element;
}

test('renders canonical and legacy elements and dispatches bubbling loaded events', async () => {
	const loaded = vi.fn();
	fixture.addEventListener('jooosi-icon:loaded', loaded);
	const canonical = await mount();
	const legacy = await mount(icon('omni-icon'));
	for (const element of [canonical, legacy]) {
		expect(element.querySelector('svg')?.getAttribute('viewBox')).toBe('0 0 24 24');
		expect(element.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
		expect(element.querySelector('svg')?.getAttribute('focusable')).toBe('false');
	}
	expect(loaded).toHaveBeenCalledTimes(2);
});

test('batches SVG writes and restores original attributes when overrides are removed', async () => {
	const element = await mount();
	const svg = element.querySelector('svg')!;
	const writes = vi.spyOn(svg, 'setAttribute');
	element.setAttribute('fill', 'red');
	element.setAttribute('stroke', 'blue');
	element.setAttribute('data-label', 'sample');
	await tick();
	expect(writes).toHaveBeenCalledTimes(3);
	expect(svg.getAttribute('fill')).toBe('red');
	writes.mockClear();
	element.setAttribute('fill', 'red');
	element.setAttribute('data-oiwc-expanded', 'true');
	await tick();
	expect(writes).not.toHaveBeenCalled();
	element.removeAttribute('fill');
	element.removeAttribute('stroke');
	element.removeAttribute('data-label');
	element.setAttribute('style', 'opacity: 0.5');
	await tick();
	expect(svg.getAttribute('fill')).toBe('currentColor');
	expect(svg.hasAttribute('stroke')).toBe(false);
	expect(svg.hasAttribute('data-label')).toBe(false);
	element.removeAttribute('style');
	await tick();
	expect(svg.getAttribute('style')).toBe('overflow: visible');
	expect(fetchMock).toHaveBeenCalledTimes(1);
});

test('coalesces multiple name changes into one render of the final name', async () => {
	const element = await mount();
	const loaded = vi.fn();
	element.addEventListener('jooosi-icon:loaded', loaded);
	const next = event(element);
	element.setAttribute('name', iconName());
	element.setAttribute('name', iconName());
	const finalName = iconName();
	element.setAttribute('name', finalName);
	expect((await next).detail.iconName).toBe(finalName);
	await tick();
	expect(fetchMock).toHaveBeenCalledTimes(2);
	expect(loaded).toHaveBeenCalledTimes(1);
});

test('preserves prerendered content and begins rendering when the marker is removed', async () => {
	const element = icon();
	element.setAttribute('data-prerendered', '');
	element.innerHTML = '<svg data-server="true"></svg>';
	fixture.append(element);
	await tick();
	expect(fetchMock).not.toHaveBeenCalled();
	expect(element.querySelector('svg')?.hasAttribute('data-server')).toBe(true);
	const loaded = event(element);
	element.removeAttribute('data-prerendered');
	await loaded;
	expect(fetchMock).toHaveBeenCalledTimes(1);
	expect(element.querySelector('path')).not.toBeNull();
});

test('does not attach a renderer after removal and attaches only once after rapid reconnects', async () => {
	const removed = icon();
	fixture.append(removed);
	removed.remove();
	await tick();
	expect(fetchMock).not.toHaveBeenCalled();
	const element = icon();
	const loaded = vi.fn();
	element.addEventListener('jooosi-icon:loaded', loaded);
	const next = event(element);
	fixture.append(element);
	element.remove();
	fixture.append(element);
	await next;
	await tick();
	expect(loaded).toHaveBeenCalledTimes(1);
	expect(fetchMock).toHaveBeenCalledTimes(1);
});

test('aborts a detached render and ignores its late result after reconnection', async () => {
	const old = deferred<Response>();
	fetchMock.mockReturnValueOnce(old.promise);
	const element = icon();
	const loaded = vi.fn();
	element.addEventListener('jooosi-icon:loaded', loaded);
	fixture.append(element);
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
	const signal = fetchMock.mock.calls[0][1].signal;
	element.remove();
	expect(signal.aborted).toBe(true);
	const next = event(element);
	fixture.append(element);
	await next;
	old.resolve(response());
	await tick();
	expect(loaded).toHaveBeenCalledTimes(1);
	expect(fetchMock).toHaveBeenCalledTimes(2);
});

test('can pause an active request for prerendered content and resume the same icon', async () => {
	const old = deferred<Response>();
	fetchMock.mockReturnValueOnce(old.promise);
	const element = icon();
	fixture.append(element);
	await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
	element.setAttribute('data-prerendered', '');
	element.innerHTML = '<svg data-server="true"></svg>';
	await tick();
	expect(fetchMock.mock.calls[0][1].signal.aborted).toBe(true);
	old.resolve(response());
	await tick();
	expect(element.querySelector('svg')?.hasAttribute('data-server')).toBe(true);
	const loaded = event(element);
	element.removeAttribute('data-prerendered');
	await loaded;
	expect(element.querySelector('path')).not.toBeNull();
});

test('keeps error styling, keyboard interaction, fallback SVGs, and recovery', async () => {
	const element = icon();
	element.setAttribute('name', 'invalid');
	const failed = event(element, 'error');
	fixture.append(element);
	expect((await failed).detail.type).toBe('INVALID_FORMAT');
	expect(element.querySelector('svg')).not.toBeNull();
	expect(element.getAttribute('role')).toBe('button');
	expect(element.getAttribute('tabindex')).toBe('0');
	expect(getComputedStyle(element).borderTopStyle).toBe('dashed');
	element.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
	expect(element.getAttribute('aria-expanded')).toBe('true');
	expect(document.querySelector<HTMLElement>('.oiwc-error-panel')?.style.display).toBe('block');
	element.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));
	expect(element.getAttribute('aria-expanded')).toBe('false');
	const recovered = event(element);
	element.setAttribute('name', iconName());
	expect((await recovered).detail.wasError).toBe(true);
	expect(element.hasAttribute('role')).toBe(false);
	expect(element.hasAttribute('tabindex')).toBe(false);
	expect(element.hasAttribute('data-oiwc-state')).toBe(false);
});

test('connects icons after the iframe body is replaced', async () => {
	const previousBody = document.body;
	const nextBody = document.createElement('body');
	const element = icon('omni-icon');
	const loaded = event(element);
	nextBody.append(element);
	previousBody.replaceWith(nextBody);
	try {
		await loaded;
		expect(element.querySelector('svg')).not.toBeNull();
	} finally {
		nextBody.replaceWith(previousBody);
	}
});
