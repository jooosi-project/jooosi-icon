/**
 * Jooosi Icon Picker Integration for LiveCanvas
 * 
 * Main entry point that initializes the icon picker integration for LiveCanvas editor.
 */
import { openIconPicker, closeIconPicker, renderModal } from './editor-app';
import './editor.scss';


(async () => {
	// Wait for DOM to be ready
	if (document.readyState === 'loading') {
		await new Promise(resolve => {
			document.addEventListener('DOMContentLoaded', resolve);
		});
	}


	// Initialize modal container
	renderModal();

	// Expose API to window for LiveCanvas panel to use
	(window as any).jooosiIconPicker = {
		open: (initialValue?: string, callback?: (iconName: string) => void) => {
			openIconPicker(initialValue || '', callback);
		},
		close: closeIconPicker,
	};

})();
