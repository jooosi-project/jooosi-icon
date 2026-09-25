import type { JooosiIconRenderer } from './JooosiIconRenderer';

let renderer: JooosiIconRenderer | null = null;
let rendererPromise: Promise<JooosiIconRenderer> | null = null;
const connections = new WeakMap<Element, object>();

function loadRenderer(): Promise<JooosiIconRenderer> {
	return rendererPromise ??= import('./JooosiIconRenderer').then((module) => {
		renderer = new module.JooosiIconRenderer();
		return renderer;
	});
}

export function connectElement(element: Element): void {
	if (connections.has(element) || !element.isConnected || element.hasAttribute('data-prerendered')) return;

	const connection = {};
	connections.set(element, connection);
	loadRenderer().then((instance) => {
		// A disconnect/reconnect during the import must not attach a stale render.
		if (connections.get(element) === connection && element.isConnected) {
			instance.attachRenderer(element);
		}
	});
}

export function disconnectElement(element: Element): void {
	connections.delete(element);
	renderer?.detachRenderer(element);
}

/** Compatibility bootstrap for documents without native custom elements. */
export function initializeObserver(): void {
	if (!('customElements' in window)) {
		import('./DocumentIconObserver').then(({ initializeDocumentObserver }) => initializeDocumentObserver());
	}
}

initializeObserver();

export const getRenderer = (): JooosiIconRenderer | null => renderer;
