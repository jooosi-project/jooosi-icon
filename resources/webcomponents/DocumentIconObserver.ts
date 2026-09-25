import { connectElement, disconnectElement } from './JooosiIconObserver';

// Loaded only in browsers without customElements. Native elements use callbacks.
const observer = new MutationObserver((mutations) => {
	for (const { addedNodes, removedNodes } of mutations) {
		addedNodes.forEach((node) => visitIcons(node, connectElement));
		removedNodes.forEach((node) => visitIcons(node, (element) => {
			if (!element.isConnected) disconnectElement(element);
		}));
	}
});

function visitIcons(node: Node, visit: (element: Element) => void): void {
	if (node.nodeType !== Node.ELEMENT_NODE) return;
	const element = node as Element;
	const selector = 'jooosi-icon, omni-icon';
	if (element.matches(selector)) visit(element);
	element.querySelectorAll(selector).forEach(visit);
}

export function initializeDocumentObserver(): void {
	if (!document.body) {
		document.addEventListener('DOMContentLoaded', initializeDocumentObserver, { once: true });
		return;
	}
	visitIcons(document.body, connectElement);
	observer.observe(document.body, { childList: true, subtree: true });
}
