import type { JooosiIconRenderer } from './JooosiIconRenderer';

/**
 * Global Jooosi Icon observer
 * 
 * This module manages lazy-loading of the JooosiIconRenderer and tracks which
 * jooosi-icon and legacy omni-icon elements have been registered for rendering.
 */

// Singleton renderer instance (lazy-loaded)
let renderer: JooosiIconRenderer | null = null;

// Promise for lazy-loading the renderer module
let rendererPromise: Promise<JooosiIconRenderer> | null = null;

// Track which elements have been seen to avoid duplicate processing
// Using WeakMap instead of WeakSet to support deletion via clearSeenElement
const seenElements = new WeakMap<Element, boolean>();

/**
 * Lazy-loads the JooosiIconRenderer module
 * Returns cached promise if already loading/loaded
 */
function loadRenderer(): Promise<JooosiIconRenderer> {
    if (!rendererPromise) {
        rendererPromise = import('./JooosiIconRenderer').then((module) => {
            renderer = new module.JooosiIconRenderer();
            return renderer;
        });
    }
    return rendererPromise;
}

function ensureRenderer(el: Element) {
    if (!['JOOOSI-ICON', 'OMNI-ICON'].includes(el.tagName)) return;

    // Already processed
    if (seenElements.has(el)) return;

    seenElements.set(el, true);

    // skip if pre-rendered
    if ((el as any).hasAttribute('data-prerendered')) {
        return;
    }

    loadRenderer().then((r) => r.attachRenderer(el));
}

function processNode(node: Node) {
    // DocumentFragment (framework inserts)
    if (node.nodeType === /* Node.DOCUMENT_FRAGMENT_NODE */ 11) {
        node.childNodes.forEach(processNode);
        return;
    }

    if (node.nodeType !== /* Node.ELEMENT_NODE */ 1) return;

    const selector = 'jooosi-icon, omni-icon';
    const targets =
        (node as Element).matches(selector)
            ? [(node as Element)]
            : Array.from((node as Element).querySelectorAll(selector));

    targets.forEach((el) => ensureRenderer(el));
}

/**
 * Global MutationObserver
 * Watches for new Jooosi Icon elements added to the DOM
 * Note: Attribute changes are handled by per-element observers in JooosiIconRenderer
 */
const observer = new MutationObserver((mutations) => {
    for (const { type, addedNodes } of mutations) {
        if (type === 'childList') {
            addedNodes.forEach(processNode);
        }
    }
});

/**
 * Scan the existing document and begin observing it once a body is available.
 * The web component can be loaded in the document head, including in the
 * Gutenberg editor, where document.body has not been created yet.
 */
function initializeObserver(): void {
    const root = document.body;

    if (!root) {
        return;
    }

    processNode(root);
    observer.observe(root, {
        childList: true,
        subtree: true,
    });
}

if (document.body) {
    initializeObserver();
} else {
    document.addEventListener('DOMContentLoaded', initializeObserver, { once: true });
}

/**
 * Get the current renderer instance (may be null if not yet loaded)
 */
export const getRenderer = (): JooosiIconRenderer | null => renderer;

/**
 * Mark element as not seen so it can be re-attached if added back to DOM
 * Called when element is disconnected to allow re-initialization on reconnect
 */
export const clearSeenElement = (el: Element): void => {
    seenElements.delete(el);
};
