/**
 * Jooosi Icon Picker Integration for ACF
 * 
 * Main entry point that initializes the icon picker for ACF fields.
 */
import { openIconPicker, closeIconPicker, renderModal } from './editor-app';
import './editor.scss';

(async () => {
	// Initialize modal container
	renderModal();

	// Helper function to get field container
	function getFieldContainer(element: HTMLElement): HTMLElement | null {
		return element.closest('.acf-jooosi-icon-field');
	}

	// Helper function to get input element
	function getInputElement(container: HTMLElement): HTMLInputElement | null {
		return container.querySelector('.acf-jooosi-icon-input');
	}

	// Helper function to update field value
	function updateFieldValue(container: HTMLElement, iconName: string) {
		const input = getInputElement(container);
		if (!input) return;

		// Update input value
		const oldValue = input.value;
		input.value = iconName;

		// Trigger change event for ACF
		const event = new CustomEvent('change', { 
			bubbles: true,
			detail: { oldValue, newValue: iconName }
		});
		input.dispatchEvent(event);

		// Update preview
		updateFieldPreview(container, iconName);
	}

	// Helper function to update field preview
	function updateFieldPreview(container: HTMLElement, iconName: string) {
		const preview = container.querySelector('.acf-jooosi-icon-preview');
		if (!preview) return;

		if (iconName) {
			preview.innerHTML = `
				<div class="acf-jooosi-icon-display">
					<jooosi-icon name="${iconName}" width="32" height="32"></jooosi-icon>
					<div class="acf-jooosi-icon-name">
						<code>${iconName}</code>
					</div>
				</div>
			`;

			// Add/update remove button
			const controls = container.querySelector('.acf-jooosi-icon-controls');
			let removeBtn = controls?.querySelector('.acf-jooosi-icon-remove');
			
			if (!removeBtn && controls) {
				removeBtn = document.createElement('button');
				removeBtn.type = 'button';
				removeBtn.className = 'button acf-jooosi-icon-remove';
				removeBtn.setAttribute('data-action', 'remove');
				removeBtn.textContent = 'Remove Icon';
				controls.appendChild(removeBtn);
			}
		} else {
			preview.innerHTML = `
				<div class="acf-jooosi-icon-placeholder">
					<p>No icon selected</p>
				</div>
			`;

			// Remove the remove button
			const removeBtn = container.querySelector('.acf-jooosi-icon-remove');
			if (removeBtn) {
				removeBtn.remove();
			}
		}
	}

	// Expose API to window for ACF field to use
	(window as any).jooosiIconPicker = {
		open: (initialValue?: string, callback?: (iconName: string) => void) => {
			openIconPicker(initialValue || '', callback || (() => {}));
		},
		close: closeIconPicker,
	};

	// Set up event listener for icon picker button clicks
	// Use event delegation since fields can be dynamically added/removed
	document.addEventListener('click', (e) => {
		const target = e.target as HTMLElement;
		
		// Handle browse button
		if (target.matches('.acf-jooosi-icon-browse') || target.closest('.acf-jooosi-icon-browse')) {
			e.preventDefault();
			e.stopPropagation();
			
			const button = target.closest('.acf-jooosi-icon-browse') as HTMLElement;
			const container = getFieldContainer(button);
			if (!container) return;
			
			const input = getInputElement(container);
			const currentValue = input?.value || '';
			
			openIconPicker(currentValue, (iconName: string) => {
				updateFieldValue(container, iconName);
			});
		}
		
		// Handle remove button
		if (target.matches('.acf-jooosi-icon-remove') || target.closest('.acf-jooosi-icon-remove')) {
			e.preventDefault();
			e.stopPropagation();
			
			const button = target.closest('.acf-jooosi-icon-remove') as HTMLElement;
			const container = getFieldContainer(button);
			if (!container) return;
			
			updateFieldValue(container, '');
		}
	});

	// Listen for ACF's append event to initialize new fields
	if ((window as any).acf) {
		(window as any).acf.addAction('append', function($el: any) {
			// Fields are automatically handled by event delegation
			console.log('[Jooosi Icon ACF] Field appended, ready for interaction');
		});
	}
})();
