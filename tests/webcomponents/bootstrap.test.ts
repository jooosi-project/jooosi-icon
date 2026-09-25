import { expect, test, vi } from 'vite-plus/test';
import { iconName, response, tick } from './helpers';

test('keeps server-rendered pages lazy and avoids observing unrelated DOM additions', async () => {
	const fetchMock = vi.fn(async () => response());
	vi.stubGlobal('fetch', fetchMock);
	const observed = vi.spyOn(MutationObserver.prototype, 'observe');
	const element = document.createElement('jooosi-icon');
	element.setAttribute('data-prerendered', '');
	element.innerHTML = '<svg data-server="true"></svg>';
	document.body.append(element);
	try {
		await import('../../resources/webcomponents/jooosi-icon');
		const { getRenderer } = await import('../../resources/webcomponents/JooosiIconObserver');
		expect(getRenderer()).toBeNull();
		expect(fetchMock).not.toHaveBeenCalled();
		expect(observed.mock.calls.some(([target]) => target === document.body)).toBe(false);

		const pending = document.createElement('omni-icon');
		pending.setAttribute('name', iconName());
		document.body.append(pending);
		pending.remove();
		await vi.waitFor(() => expect(getRenderer()).not.toBeNull());
		await tick();
		expect(fetchMock).not.toHaveBeenCalled();
	} finally {
		element.remove();
		vi.restoreAllMocks();
		vi.unstubAllGlobals();
	}
});
