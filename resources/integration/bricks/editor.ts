/**
 * Jooosi Icon Picker Integration for Bricks Builder
 * 
 * Main entry point that waits for Bricks to load and initializes the integration.
 */
import { openIconPicker, closeIconPicker, renderModal } from './editor-app';
import './editor.scss';


(async () => {

	// Wait for Bricks Vue app to be ready
	while (!(document.querySelector('.brx-body') as any)?.__vue_app__) {
		await new Promise(resolve => setTimeout(resolve, 100));
	}

	// Wait for preloader to disappear
	while (document.getElementById('bricks-preloader')) {
		await new Promise(resolve => setTimeout(resolve, 100));
	}

	// Wait for iframe Vue app to be ready
	const iframeElement = document.getElementById('bricks-builder-iframe') as HTMLIFrameElement | null;
	if (!iframeElement) {
		console.error('[Jooosi Icon] Could not find bricks-builder-iframe. Aborting integration.');
		return;
	}

	while (!(iframeElement.contentDocument?.querySelector('.brx-body') as any)?.__vue_app__) {
		await new Promise(resolve => setTimeout(resolve, 100));
	}


	// Initialize modal container
	renderModal();

	// Helper function to get current icon value from Bricks
	function getCurrentIconValue(): string {
		// Try to get value from the input field
		const input = document.querySelector('input[id="iconName"]') as HTMLInputElement;
		if (input && input.value) {
			return input.value;
		}
		
		// Try to get value from Bricks Vue state
		const vueApp = (document.querySelector('.brx-body') as any)?.__vue_app__;
		if (vueApp?.config?.globalProperties?.$root?.settings?.iconName) {
			return vueApp.config.globalProperties.$root.settings.iconName;
		}
		
		return '';
	}

	// Helper function to update icon value in Bricks
	function updateIconValue(iconName: string) {
		// Update the input field
		const input = document.querySelector('input[id="iconName"]') as HTMLInputElement;
		if (input) {
			input.value = iconName;
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}
		
		// Also update Vue state directly if available
		const vueApp = (document.querySelector('.brx-body') as any)?.__vue_app__;
		if (vueApp?.config?.globalProperties?.$root?.settings) {
			vueApp.config.globalProperties.$root.settings.iconName = iconName;
			// Trigger Vue reactivity
			vueApp.config.globalProperties.$root.$forceUpdate?.();
		}
	}

	// Expose API to window for PHP to use
	(window as any).jooosiIconPicker = {
		open: (initialValue?: string, callback?: (iconName: string) => void) => {
			// If no initial value provided, get it from Bricks
			const currentValue = initialValue !== undefined ? initialValue : getCurrentIconValue();
			
			// Wrap callback to also update Bricks state
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

})();
