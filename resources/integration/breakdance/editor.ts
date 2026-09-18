/**
 * Jooosi Icon Picker Integration for Breakdance Builder
 * 
 * Main entry point that initializes the integration for Breakdance Element Studio.
 * This monitors the UI and hooks into the Browse button to open the icon picker.
 */
import { openIconPicker, closeIconPicker, renderModal } from './editor-app';
import './editor.scss';


(async () => {
	// Wait for Breakdance Vue app to be ready
	while (!(document.querySelector('#app') as any)?.__vue__) {
		await new Promise(resolve => setTimeout(resolve, 100));
	}


	// Initialize modal container
	renderModal();

	// Get Vue instance
	const vueApp = (document.querySelector('#app') as any).__vue__;
	const vueStore = vueApp.$store;

	// Expose API to window
	(window as any).jooosiIconPicker = {
		open: (initialValue?: string, callback?: (iconName: string) => void) => {
			const currentValue = initialValue || '';
			openIconPicker(currentValue, callback);
		},
		close: closeIconPicker,
	};

	// Handle browse button click
	function handleBrowseClick() {
		const activeElement = vueStore.getters['ui/activeElement'];
		if (!activeElement) {
			return;
		}

		// Get current icon name value
		const currentIconName = activeElement.data?.properties?.content?.icon?.name || '';


		// Open icon picker
		openIconPicker(currentIconName, (iconName: string) => {
			updateIconName(iconName);
		});
	}

	// Update icon name in Breakdance
	function updateIconName(iconName: string) {
		// Find the input field and update it directly
		const input = document.querySelector('div[data-test-id="control-content-icon-name"] input') as HTMLInputElement;
		if (input) {
			input.value = iconName;
			// Trigger input and change events to notify Vue
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}

		// Also try to update via Vue store if available
		const activeElement = vueStore.getters['ui/activeElement'];
		if (activeElement) {
			// Ensure the path exists
			if (!activeElement.data.properties.content) {
				activeElement.data.properties.content = {};
			}
			if (!activeElement.data.properties.content.icon) {
				activeElement.data.properties.content.icon = {};
			}

			// Update the value
			activeElement.data.properties.content.icon.name = iconName;
		}
	}

	// Monitor button clicks using event delegation
	document.addEventListener('click', (event) => {
		const target = event.target as HTMLElement;

		// Check if the click is on the browse button or its children
		const isInsideIconPicker = target.closest('div[data-test-id="control-content-icon-icon_picker"]');
		const isTriggerButton = target.classList.contains('breakdance-trigger-action-button') ||
			target.closest('button.breakdance-trigger-action-button');

		if (isInsideIconPicker && isTriggerButton) {
			// Check if we're in an JooosiIcon element by looking for the icon name control
			// instead of relying on activeElement.type which may be undefined
			const iconNameControl = document.querySelector('div[data-test-id="control-content-icon-name"]');

			if (iconNameControl) {
				event.preventDefault();
				event.stopPropagation();
				handleBrowseClick();
			}
		}
	}, true); // Use capture phase to ensure we catch it

})();
