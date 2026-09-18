/**
 * Jooosi Icon Picker Integration for Breakdance Builder
 * 
 * Main entry point that initializes the integration for Breakdance Element Studio.
 * This monitors the UI and hooks into the Browse button to open the icon picker.
 */
import { openIconPicker, closeIconPicker, renderModal } from './editor-app';
// The picker renders <jooosi-icon> elements in the Breakdance UI document.
// Breakdance loads the web component in its canvas iframe, so register it in
// the parent builder document as well.
import '../../webcomponents/jooosi-icon';
import './editor.scss';


(async () => {
	// Breakdance 2.x uses Vue 3/Pinia. Older versions exposed the Vue 2
	// instance as `__vue__`, so support both app shapes here.
	let appElement: any;
	while (true) {
		appElement = document.querySelector('#app') as any;
		if (appElement?.__vue__ || appElement?.__vue_app__) {
			break;
		}

		await new Promise(resolve => setTimeout(resolve, 100));
	}

	// Initialize modal container
	renderModal();

	const vueStore = appElement.__vue__?.$store;
	const pinia = appElement.__vue_app__?.config?.globalProperties?.$pinia;
	const uiStore = pinia?._s?.get('ui');
	const documentStore = pinia?._s?.get('document');

	function getActiveElement() {
		return vueStore?.getters?.['ui/activeElement'] || uiStore?.activeElement;
	}

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
		const activeElement = getActiveElement();
		if (!activeElement) {
			return;
		}

		// Get current icon name value
		const currentIconName = activeElement.data?.properties?.content?.icon?.name ||
			(document.querySelector('div[data-test-id="control-content-icon-name"] input') as HTMLInputElement)?.value || '';


		// Open icon picker
		openIconPicker(currentIconName, (iconName: string) => {
			updateIconName(iconName);
		});
	}

	// Update icon name in Breakdance
	function updateIconName(iconName: string) {
		const activeElement = getActiveElement();
		if (activeElement) {
			// Use Breakdance's document action when available so the change is
			// reactive and participates in Breakdance's undo/save flow.
			if (typeof documentStore?.throttledPropertyChanged === 'function' && activeElement.id !== undefined) {
				documentStore.throttledPropertyChanged({
					elementId: activeElement.id,
					path: 'content.icon.name',
					value: iconName,
					meta: { snapshotLabel: 'Select icon' },
				});
			} else {
				// Fallback for older Breakdance versions.
				activeElement.data ||= {};
				activeElement.data.properties ||= {};
				activeElement.data.properties.content ||= {};
				activeElement.data.properties.content.icon ||= {};
				activeElement.data.properties.content.icon.name = iconName;
			}
		}

		// Keep the visible control in sync for both Vue 2 and Vue 3 controls.
		const input = document.querySelector('div[data-test-id="control-content-icon-name"] input') as HTMLInputElement;
		if (input) {
			input.value = iconName;
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	// Monitor button clicks using event delegation
	document.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		if (!target) {
			return;
		}

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
