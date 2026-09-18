/**
 * Jooosi Icon Picker Integration for Elementor
 * 
 * Main entry point that waits for Elementor to load and initializes the integration.
 */
import { openIconPicker, closeIconPicker, renderModal } from './editor-app';
import './editor.scss';


(async () => {
	// Wait for Elementor to be ready
	while (!(window as any).elementor) {
		await new Promise(resolve => setTimeout(resolve, 100));
	}

	// Wait for Elementor panel to be ready
	while (!(window as any).elementor?.panel) {
		await new Promise(resolve => setTimeout(resolve, 100));
	}


	// Initialize modal container
	renderModal();

	// Helper function to get current icon value from Elementor
	function getCurrentIconValue(): string {
		try {
			const elementor = (window as any).elementor;
			const currentElement = elementor.getCurrentElement();
			
			if (currentElement) {
				const model = currentElement.model;
				const settings = model.get('settings');
				const iconName = settings.get('icon_name');
				
				if (iconName) {
					return iconName;
				}
			}
		} catch (error) {
			console.warn('[Jooosi Icon] Could not get current icon value:', error);
		}
		
		return '';
	}

	// Helper function to update icon value in Elementor
	function updateIconValue(iconName: string) {
		try {
			const elementor = (window as any).elementor;
			
			// Find the input field directly in the DOM
			const input = document.querySelector('input[data-setting="icon_name"]') as HTMLInputElement;
			if (input) {
				// Update the input value
				input.value = iconName;
				
				// Trigger native input and change events
				input.dispatchEvent(new Event('input', { bubbles: true }));
				input.dispatchEvent(new Event('change', { bubbles: true }));
			}
			
			// Also update the model directly
			const currentElement = elementor.getCurrentElement();
			if (currentElement) {
				currentElement.model.setSetting('icon_name', iconName);
			}
		} catch (error) {
			console.error('[Jooosi Icon] Could not update icon value:', error);
		}
	}

	// Expose API to window for Elementor control to use
	(window as any).jooosiIconPicker = {
		open: (initialValue?: string, callback?: (iconName: string) => void) => {
			// If no initial value provided, get it from Elementor
			const currentValue = initialValue !== undefined ? initialValue : getCurrentIconValue();
			
			// Wrap callback to also update Elementor state
			const wrappedCallback = (iconName: string) => {
				updateIconValue(iconName);
				if (callback) {
					callback(iconName);
				}
			};
			
			openIconPicker(currentValue, wrappedCallback);
		},
		close: closeIconPicker,
	};

	// Set up event listener for icon picker button clicks
	// This needs to be delegated since controls are dynamically added/removed
	const checkForButtons = () => {
		const buttons = document.querySelectorAll('.oiel-icon-picker-button:not([data-listener-attached])');
		buttons.forEach(button => {
			button.setAttribute('data-listener-attached', 'true');
			button.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				
				const currentValue = getCurrentIconValue();
				openIconPicker(currentValue, (iconName: string) => {
					updateIconValue(iconName);
				});
			});
		});
	};

	// Check for buttons periodically when panel is open
	setInterval(checkForButtons, 500);

	// Also check when panel changes
	(window as any).elementor.channels.editor.on('change', checkForButtons);

})();
