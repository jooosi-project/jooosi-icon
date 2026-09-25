import { expect, test, vi } from 'vite-plus/test';
import { iconName, response } from './helpers';

test('keeps dynamic rendering and cancellation working without native custom elements', async () => {
	const descriptor = Object.getOwnPropertyDescriptor(window, 'customElements')!;
	const fetchMock = vi.fn(async () => response());
	vi.stubGlobal('fetch', fetchMock);
	Reflect.deleteProperty(window, 'customElements');
	const fixture = document.createElement('div');
	const existing = document.createElement('jooosi-icon');
	existing.setAttribute('name', iconName());
	fixture.append(existing);
	document.body.append(fixture);
	try {
		await import('../../resources/webcomponents/jooosi-icon');
		await vi.waitFor(() => expect(existing.querySelector('svg')).not.toBeNull());
		const dynamic = document.createElement('omni-icon');
		dynamic.setAttribute('name', iconName());
		fixture.append(dynamic);
		await vi.waitFor(() => expect(dynamic.querySelector('svg')).not.toBeNull());
		expect(fetchMock).toHaveBeenCalledTimes(2);

		fetchMock.mockImplementation((_url, { signal }) => new Promise((_resolve, reject) => {
			signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true });
		}));
		dynamic.setAttribute('name', iconName());
		await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(3));
		const signal = fetchMock.mock.calls[2][1].signal;
		dynamic.remove();
		await vi.waitFor(() => expect(signal.aborted).toBe(true));
	} finally {
		fixture.remove();
		Object.defineProperty(window, 'customElements', descriptor);
		vi.unstubAllGlobals();
	}
});
