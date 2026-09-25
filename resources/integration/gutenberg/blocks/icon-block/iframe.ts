import '../../../../webcomponents/jooosi-icon';
import { initializeObserver } from '../../../../webcomponents/JooosiIconObserver';
import iconStylesheetUrl from '../../../../webcomponents/jooosi-icon.scss?url';
import iframeStylesheetUrl from './iframe.css?url';

const styleAttribute = 'data-jooosi-icon-gutenberg-style';
let observedBody: HTMLBodyElement | null = null;

/**
 * Load the styles into the iframe document itself. WordPress can construct
 * the editor canvas from a blob document, so relying only on the parent
 * document's stylesheet queue is not sufficient here.
 */
function loadStyles(): void {
	if (!document.head) {
		return;
	}

	[iconStylesheetUrl, iframeStylesheetUrl].forEach((stylesheetUrl) => {
		const href = new URL(stylesheetUrl, import.meta.url).href;
		const alreadyLoaded = Array.from(
			document.head.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]'),
		).some((link) => link.href === href);

		if (alreadyLoaded) {
			return;
		}

		const link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		link.setAttribute(styleAttribute, 'true');
		document.head.appendChild(link);
	});
}

/**
 * Native custom elements reconnect automatically when the iframe body changes.
 * Reattach the compatibility observer for browsers without custom elements.
 */
function initializeGutenbergCanvas(): void {
	loadStyles();

	if (document.body && document.body !== observedBody) {
		observedBody = document.body;
		initializeObserver();
	}
}

function startGutenbergBootstrap(): void {
	initializeGutenbergCanvas();

	// Catch a body replacement immediately, then use a short bounded retry
	// window for editors that create the canvas asynchronously.
	const documentObserver = new MutationObserver(initializeGutenbergCanvas);
	if (document.documentElement) {
		documentObserver.observe(document.documentElement, { childList: true });
	}

	let retries = 0;
	const retry = (): void => {
		initializeGutenbergCanvas();

		if (retries < 20) {
			retries += 1;
			window.setTimeout(retry, 100);
		}
	};

	retry();
}

/**
 * Gutenberg can inject this entry after the iframe's DOMContentLoaded event.
 * Start the Gutenberg-only bootstrap so the shared web component does not
 * need editor-specific timing workarounds.
 */
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', startGutenbergBootstrap, { once: true });
} else {
	startGutenbergBootstrap();
}
