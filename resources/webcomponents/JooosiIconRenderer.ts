import { IconError, IconErrorType, fetchIcon as fetchIconFromRegistry } from './IconRegistry';

interface ParsedIconName {
	prefix: string;
	name: string;
}

interface IconConfig {
	name: string;
	width: string;
	height: string;
	color?: string;
}

export type IconStatus = 'rendered' | 'loading' | 'failed';

export interface IconRendererState {
	renderStatus: IconStatus;
	renderAbortController: AbortController | null;
	lastError: IconError | null;
	activeIconName: string | null;
	mutationObserver: MutationObserver | null;
	originalWidth: string | null;
	originalHeight: string | null;
	cachedSvgElement: SVGSVGElement | null;
}

/** Renders canonical and legacy Jooosi Icon custom elements. */
export class JooosiIconRenderer {
	private static readonly RESERVED_ATTRS = new Set(['name', 'id', 'role', 'aria-expanded', 'tabindex']);
	private static readonly SVG_ATTRS = new Set(['xmlns', 'viewBox', 'aria-hidden', 'focusable']);
	private static readonly CLEANUP_ATTRS = ['data-oiwc-state', 'data-oiwc-error-type', 'data-oiwc-original-icon', 'data-oiwc-expanded', 'role', 'aria-expanded', 'tabindex'];

	private states = new WeakMap<Element, IconRendererState>();
	
	// Lazy load ErrorObserver only when first error occurs
	private static errorObserverPromise: Promise<void> | null = null;
	private static errorObserverLoaded = false;

	private getState(element: Element): IconRendererState {
		let state = this.states.get(element);
		if (!state) {
			state = {
				renderStatus: 'loading',
				renderAbortController: null,
				lastError: null,
				activeIconName: null,
				mutationObserver: null,
				originalWidth: null,
				originalHeight: null,
				cachedSvgElement: null,
			};
			this.states.set(element, state);
		}
		return state;
	}

	attachRenderer(element: Element): void {
		const state = this.getState(element);

		// Skip rendering if data-prerendered attribute is present
		if (element.hasAttribute('data-prerendered')) {
			state.renderStatus = 'rendered';
			// Still observe for attribute changes that might require re-rendering
			this.observeElement(element);
			return;
		}

		this.renderIcon(element);
		this.observeElement(element);
	}

	detachRenderer(element: Element): void {
		const state = this.getState(element);
		
		state.renderAbortController?.abort();
		state.renderAbortController = null;
		state.activeIconName = null;
		state.originalWidth = null;
		state.originalHeight = null;
		state.cachedSvgElement = null;

		if (state.mutationObserver) {
			state.mutationObserver.disconnect();
			state.mutationObserver = null;
		}

		this.states.delete(element);
	}

	restartAnimation(element: Element): void {
		const state = this.getState(element);
		const svgElement = state.cachedSvgElement || element.querySelector('svg');
		if (svgElement && state.renderStatus === 'rendered') {
			try {
				(svgElement as SVGSVGElement).setCurrentTime?.(0);
			} catch {
				this.renderIcon(element);
			}
		}
	}

	private observeElement(element: Element): void {
		const state = this.getState(element);

		// Don't create observer if already exists
		if (state.mutationObserver) {
			return;
		}

		// Watch for all attribute changes
		state.mutationObserver = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				if (mutation.type === 'attributes') {
					const attributeName = mutation.attributeName;

					// If data-prerendered is added, stop observing and preserve content
					if (attributeName === 'data-prerendered' && element.hasAttribute('data-prerendered')) {
						state.renderAbortController?.abort();
						state.renderStatus = 'rendered';
						return;
					}

					// If data-prerendered is removed, start rendering
					if (attributeName === 'data-prerendered' && !element.hasAttribute('data-prerendered')) {
						this.renderIcon(element);
						return;
					}

					// Skip all updates if data-prerendered is present
					if (element.hasAttribute('data-prerendered')) {
						return;
					}

					// If name changed, re-fetch the icon
					if (attributeName === 'name') {
						this.renderIcon(element);
					}

					// For other attributes, just update the SVG if already rendered
					else if (state.renderStatus === 'rendered') {
						this.updateSvgAttributes(element);
					}
				}
			});
		});

		state.mutationObserver.observe(element, {
			attributes: true,
			attributeOldValue: false, // We don't use oldValue anywhere - saves memory
		});
	}

	private getConfig(element: Element): IconConfig {
		const state = this.getState(element);
		const attrWidth = element.getAttribute('width');
		const attrHeight = element.getAttribute('height');

		// Determine dimensions: attribute > original
		let width = attrWidth || state.originalWidth || '';
		let height = attrHeight || state.originalHeight || '';

		// If one dimension is specified but not the other, match them
		if (attrWidth && !attrHeight) {
			height = attrWidth;
		} else if (attrHeight && !attrWidth) {
			width = attrHeight;
		}

		return {
			name: element.getAttribute('name') || '',
			width,
			height,
			color: element.getAttribute('color') || undefined,
		};
	}

	private parseIconName(iconName: string): ParsedIconName {
		if (!iconName) {
			throw new IconError(IconErrorType.NO_NAME, 'No icon name specified');
		}

		const separatorIndex = iconName.indexOf(/* JooosiIconRenderer.ICON_NAME_SEPARATOR */ ':');

		if (separatorIndex === -1 || separatorIndex === 0 || separatorIndex === iconName.length - 1) {
			throw new IconError(
				IconErrorType.INVALID_FORMAT,
				`Invalid icon format: "${iconName}". Expected format: "prefix:name"`
			);
		}

		return {
			prefix: iconName.substring(0, separatorIndex).trim(),
			name: iconName.substring(separatorIndex + 1).trim(),
		};
	}

	private async renderIcon(element: Element): Promise<void> {
		const state = this.getState(element);

		if (element.hasAttribute('data-prerendered')) {
			return;
		}

		const config = this.getConfig(element);
		const targetIconName = config.name;
		const iconChanged = targetIconName !== state.activeIconName;

		// Abort previous render if icon changed
		if (iconChanged && state.renderAbortController) {
			state.renderAbortController.abort();
			state.renderAbortController = null;
		}

		// Update active icon name
		if (iconChanged) {
			state.activeIconName = targetIconName || null;
		}

		// Create new controller if needed
		if (!state.renderAbortController) {
			state.renderAbortController = new AbortController();
		}

		const renderController = state.renderAbortController;

		try {
			state.renderStatus = 'loading';
			this.renderLoading(element, config);

			const svg = await this.fetchIcon(config.name, renderController.signal);
			if (renderController.signal.aborted) {
				return;
			}

			this.renderSvg(element, svg, config);
		} catch (error) {
			if (renderController.signal.aborted) {
				return;
			}

			state.renderStatus = 'failed';
			const iconError = error instanceof IconError
				? error
				: new IconError(
					IconErrorType.FETCH_FAILED,
					error instanceof Error ? error.message : 'Unknown error occurred',
					error instanceof Error ? error : undefined
				);
			await this.renderError(element, iconError, renderController.signal);
		} finally {
			if (state.renderAbortController === renderController) {
				state.renderAbortController = null;
			}
		}
	}

	private renderLoading(element: Element, config: IconConfig): void {
		element.setAttribute('data-oiwc-state', 'loading');
		['data-oiwc-error-type', 'data-oiwc-original-icon', 'data-oiwc-expanded'].forEach(attr => element.removeAttribute(attr));
		if (config.width) (element as HTMLElement).style.width = this.toCssLength(config.width);
		if (config.height) (element as HTMLElement).style.height = this.toCssLength(config.height);
		element.innerHTML = '';
	}

	private renderSvg(element: Element, svg: string, config: IconConfig): void {
		const state = this.getState(element);
		const wasError = state.lastError !== null;

		element.innerHTML = svg;
		state.cachedSvgElement = element.querySelector('svg');

		if (state.cachedSvgElement) {
			// Store original dimensions from SVG before modifying
			if (!state.originalWidth) {
				state.originalWidth = state.cachedSvgElement.getAttribute('width');
			}
			if (!state.originalHeight) {
				state.originalHeight = state.cachedSvgElement.getAttribute('height');
			}

			state.cachedSvgElement.setAttribute('aria-hidden', 'true');
			state.cachedSvgElement.setAttribute('focusable', 'false');
			this.passAttributesToSvg(element, state.cachedSvgElement);
		}

		state.renderStatus = 'rendered';
		// Batch remove cleanup attributes
		JooosiIconRenderer.CLEANUP_ATTRS.forEach(attr => element.removeAttribute(attr));
		(element as HTMLElement).style.width = '';
		(element as HTMLElement).style.height = '';
		(element as HTMLElement).style.cursor = '';

		const detail = {
			iconName: config.name,
			wasError,
			element,
		};

		element.dispatchEvent(
			new CustomEvent('jooosi-icon:loaded', {
				detail,
				bubbles: true,
				composed: true,
			})
		);

		state.lastError = null;
	}

	private async renderError(element: Element, error: IconError, signal?: AbortSignal): Promise<void> {
		const state = this.getState(element);
		state.lastError = error;
		const config = this.getConfig(element);

		element.setAttribute('data-oiwc-state', 'error');
		element.setAttribute('data-oiwc-error-type', error.type);
		element.setAttribute('data-oiwc-original-icon', config.name);
		// Only set dimensions if they exist
		if (config.width) (element as HTMLElement).style.width = this.toCssLength(config.width);
		if (config.height) (element as HTMLElement).style.height = this.toCssLength(config.height);

		try {
			const fallbackSvg = await this.fetchIcon(
				/* JooosiIconRenderer.FALLBACK_ICON */ 'tabler:error-404',
				signal,
				/* JooosiIconRenderer.FALLBACK_PRIORITY */ 10
			);
			if (signal?.aborted) {
				return;
			}

			element.innerHTML = fallbackSvg;
			const svgElement = element.querySelector('svg');
			if (svgElement) {
				svgElement.setAttribute('aria-hidden', 'true');
				svgElement.setAttribute('focusable', 'false');
				this.passAttributesToSvg(element, svgElement);
			}
		} catch {
			if (signal?.aborted) {
				return;
			}

			element.innerHTML = '';
		}

		// Lazy load ErrorObserver on first error
		await this.ensureErrorObserver();

		const detail = {
			type: error.type,
			message: error.message,
			iconName: config.name,
			element,
		};

		element.dispatchEvent(
			new CustomEvent('jooosi-icon:error', {
				detail,
				bubbles: true,
				composed: true,
			})
		);

	}

	private async ensureErrorObserver(): Promise<void> {
		if (!JooosiIconRenderer.errorObserverLoaded && !JooosiIconRenderer.errorObserverPromise) {
			JooosiIconRenderer.errorObserverPromise = import('./ErrorObserver').then(({ ErrorObserver }) => {
				new ErrorObserver();
				JooosiIconRenderer.errorObserverLoaded = true;
			});
		}
		await JooosiIconRenderer.errorObserverPromise;
	}

	private shouldSkipAttribute(attrName: string): boolean {
		return attrName.startsWith('data-oiwc-') || JooosiIconRenderer.RESERVED_ATTRS.has(attrName);
	}

	private toCssLength(value: string): string {
		return /^-?\d+(?:\.\d+)?$/.test(value) ? `${value}px` : value;
	}

	private passAttributesToSvg(element: Element, svgElement: SVGSVGElement): void {
		Array.from(element.attributes).forEach((attr) => {
			if (!this.shouldSkipAttribute(attr.name)) {
				svgElement.setAttribute(attr.name, attr.value);
			}
		});
	}

	private updateSvgAttributes(element: Element): void {
		const state = this.getState(element);
		// Use cached SVG element to avoid DOM query
		const svgElement = state.cachedSvgElement || element.querySelector('svg');
		if (!svgElement) {
			return;
		}
		state.cachedSvgElement = svgElement;

		// Get current attributes on the jooosi-icon element
		const hostAttrs = new Map<string, string>();
		Array.from(element.attributes).forEach((attr) => {
			if (!this.shouldSkipAttribute(attr.name)) {
				hostAttrs.set(attr.name, attr.value);
			}
		});

		// Remove attributes from SVG that are no longer on the host
		Array.from(svgElement.attributes).forEach((attr) => {
			const attrName = attr.name;
			// Skip SVG-specific attributes
			if (!JooosiIconRenderer.SVG_ATTRS.has(attrName) &&
				!JooosiIconRenderer.RESERVED_ATTRS.has(attrName) &&
				!hostAttrs.has(attrName)) {
				svgElement.removeAttribute(attrName);
			}
		});

		// Add/update attributes from host to SVG
		hostAttrs.forEach((value, name) => {
			svgElement.setAttribute(name, value);
		});
	}

	private async fetchIcon(iconName: string, signal?: AbortSignal, priority = 0): Promise<string> {
		const { prefix, name } = this.parseIconName(iconName);
		return fetchIconFromRegistry(iconName, prefix, name, { signal, priority });
	}
}
