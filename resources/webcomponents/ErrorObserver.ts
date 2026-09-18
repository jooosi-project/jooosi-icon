interface IconErrorDetail {
	type: string;
	message: string;
	iconName: string;
	element: HTMLElement;
}

interface IconLoadedDetail {
	iconName: string;
	wasError: boolean;
	element: HTMLElement;
}

interface ErrorHandlers {
	clickHandler: (e: Event) => void;
	keyHandler: (e: KeyboardEvent) => void;
}

export class ErrorObserver {
	private errorPanel: HTMLElement | null = null;
	private currentErrorIcon: HTMLElement | null = null;
	private clickOutsideHandler: ((event: Event) => void) | null = null;
	private repositionHandler: (() => void) | null = null;
	private errorHandlers = new WeakMap<HTMLElement, ErrorHandlers>();
	private errorDetails = new WeakMap<HTMLElement, IconErrorDetail>();
	private cssLoaded = false;

	constructor() {
		document.addEventListener('jooosi-icon:error', this.handleErrorEvent);
		document.addEventListener('jooosi-icon:loaded', this.handleLoadedEvent);
	}

	private async loadCSS(): Promise<void> {
		if (this.cssLoaded) return;
		
		this.cssLoaded = true;
		const link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = new URL('./ErrorObserver.css', import.meta.url).href;
		document.head.appendChild(link);
	}

	private handleErrorEvent = (event: Event): void => {
		const detail = (event as CustomEvent<IconErrorDetail>).detail;
		if (!detail?.element) {
			return;
		}

		this.setupErrorIcon(detail);
	};

	private handleLoadedEvent = (event: Event): void => {
		const detail = (event as CustomEvent<IconLoadedDetail>).detail;
		if (!detail?.element || !detail.wasError) {
			return;
		}

		this.cleanupErrorIcon(detail.element);
	};

	private setupErrorIcon(detail: IconErrorDetail): void {
		const iconElement = detail.element;
		this.errorDetails.set(iconElement, detail);

		if (this.errorHandlers.has(iconElement)) {
			return;
		}

		// Lazy load CSS only when first error occurs
		this.loadCSS();

		iconElement.style.cursor = 'pointer';
		iconElement.setAttribute('role', 'button');
		iconElement.setAttribute('aria-expanded', 'false');
		iconElement.setAttribute('tabindex', '0');

		const togglePanel = (event: Event) => {
			event.stopPropagation();
			if (this.currentErrorIcon === iconElement) {
				this.hideErrorPanel();
				return;
			}

			const latestDetail = this.errorDetails.get(iconElement) ?? detail;
			this.showErrorPanel(iconElement, latestDetail);
		};

		const keyHandler = (event: KeyboardEvent) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				togglePanel(event);
			}
		};

		iconElement.addEventListener('click', togglePanel);
		iconElement.addEventListener('keydown', keyHandler);
		this.errorHandlers.set(iconElement, { clickHandler: togglePanel, keyHandler });
	}

	private showErrorPanel(iconElement: HTMLElement, error: IconErrorDetail): void {
		if (!this.errorPanel) {
			this.errorPanel = document.createElement('div');
			this.errorPanel.className = 'oiwc-error-panel';
			document.body.appendChild(this.errorPanel);
		}

		this.errorPanel.innerHTML = `
			<div class="oiwc-error-panel__header">
				<span class="oiwc-error-panel__title">Jooosi Icon - ${error.type}</span>
				<button class="oiwc-error-panel__close" aria-label="Close error message">×</button>
			</div>
			<p class="oiwc-error-panel__meta">
				<strong>Icon:</strong> <code>${error.iconName}</code>
			</p>
			<p class="oiwc-error-panel__message">${error.message}</p>
		`;

		this.currentErrorIcon?.setAttribute('aria-expanded', 'false');
		this.currentErrorIcon?.removeAttribute('data-oiwc-expanded');

		this.currentErrorIcon = iconElement;
		iconElement.setAttribute('aria-expanded', 'true');
		iconElement.setAttribute('data-oiwc-expanded', 'true');

		this.errorPanel.style.display = 'block';
		this.errorPanel.classList.remove('oiwc-error-panel--visible');
		this.positionPanel();

		requestAnimationFrame(() => {
			this.errorPanel?.classList.add('oiwc-error-panel--visible');
		});

		const closeBtn = this.errorPanel.querySelector<HTMLButtonElement>('.oiwc-error-panel__close');
		closeBtn?.addEventListener(
			'click',
			(event) => {
				event.stopPropagation();
				this.hideErrorPanel();
			},
			{ once: true }
		);

		this.bindGlobalHandlers();
	}

	private hideErrorPanel(): void {
		if (!this.errorPanel) {
			return;
		}

		if (this.currentErrorIcon) {
			this.currentErrorIcon.setAttribute('aria-expanded', 'false');
			this.currentErrorIcon.removeAttribute('data-oiwc-expanded');
			this.currentErrorIcon = null;
		}

		this.errorPanel.classList.remove('oiwc-error-panel--visible');
		this.errorPanel.style.display = 'none';
		this.unbindGlobalHandlers();
	}

	private bindGlobalHandlers(): void {
		this.unbindGlobalHandlers();

		this.clickOutsideHandler = (event: Event) => {
			if (!this.currentErrorIcon || !this.errorPanel) {
				return;
			}

			const path = event.composedPath();
			if (!path.includes(this.currentErrorIcon) && !path.includes(this.errorPanel)) {
				this.hideErrorPanel();
			}
		};

		setTimeout(() => {
			if (this.clickOutsideHandler) {
				document.addEventListener('click', this.clickOutsideHandler);
			}
		}, 0);

		this.repositionHandler = () => this.positionPanel();
		window.addEventListener('scroll', this.repositionHandler, true);
		window.addEventListener('resize', this.repositionHandler);
	}

	private unbindGlobalHandlers(): void {
		if (this.clickOutsideHandler) {
			document.removeEventListener('click', this.clickOutsideHandler);
			this.clickOutsideHandler = null;
		}

		if (this.repositionHandler) {
			window.removeEventListener('scroll', this.repositionHandler, true);
			window.removeEventListener('resize', this.repositionHandler);
			this.repositionHandler = null;
		}
	}

	private positionPanel(): void {
		if (!this.errorPanel || !this.currentErrorIcon) {
			return;
		}

		const padding = 8;
		const iconRect = this.currentErrorIcon.getBoundingClientRect();

		this.errorPanel.style.visibility = 'hidden';
		this.errorPanel.style.display = 'block';
		const panelRect = this.errorPanel.getBoundingClientRect();
		this.errorPanel.style.visibility = '';

		let left = iconRect.right + padding + window.scrollX;
		let top = iconRect.top + window.scrollY;

		const viewportRight = window.scrollX + window.innerWidth - padding;
		if (left + panelRect.width > viewportRight) {
			left = iconRect.left + window.scrollX - panelRect.width - padding;
		}

		if (left < padding) {
			left = padding;
		}

		const maxTop = window.scrollY + window.innerHeight - panelRect.height - padding;
		if (top > maxTop) {
			top = Math.max(window.scrollY + padding, maxTop);
		}

		this.errorPanel.style.left = `${left}px`;
		this.errorPanel.style.top = `${top}px`;
	}

	private cleanupErrorIcon(iconElement: HTMLElement): void {
		const handlers = this.errorHandlers.get(iconElement);
		if (handlers) {
			iconElement.removeEventListener('click', handlers.clickHandler);
			iconElement.removeEventListener('keydown', handlers.keyHandler);
			this.errorHandlers.delete(iconElement);
		}

		this.errorDetails.delete(iconElement);
		iconElement.style.cursor = '';
		iconElement.removeAttribute('role');
		iconElement.removeAttribute('tabindex');
		iconElement.removeAttribute('aria-expanded');
		iconElement.removeAttribute('data-oiwc-expanded');

		if (this.currentErrorIcon === iconElement) {
			this.hideErrorPanel();
		}
	}
}
