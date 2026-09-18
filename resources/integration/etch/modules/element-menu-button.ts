/**
 * Element Menu Button Module
 * 
 * This module adds an "Jooosi Icon" button to Etch's element menu
 * allowing users to insert icons directly from the element toolbar.
 */

// Helper function to get the icon picker API
const getIconPickerAPI = () => {
	return (window as any).jooosiIconPicker;
};

// Helper function to show a toast notification
const showToast = (message: string, type: 'success' | 'error' = 'success') => {
	// Create toast element
	const toast = document.createElement('div');
	toast.className = `jooosi-icon-toast jooosi-icon-toast-${type}`;
	toast.textContent = message;
	toast.style.cssText = `
		position: fixed;
		top: 20px;
		right: 20px;
		background: ${type === 'success' ? '#28a745' : '#dc3545'};
		color: white;
		padding: 12px 20px;
		border-radius: 4px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.2);
		z-index: 999999;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		font-size: 14px;
		animation: jooosiIconToastSlideIn 0.3s ease-out;
	`;
	
	// Add animation styles if not already present
	if (!document.getElementById('jooosi-icon-toast-styles')) {
		const style = document.createElement('style');
		style.id = 'jooosi-icon-toast-styles';
		style.textContent = `
			@keyframes jooosiIconToastSlideIn {
				from {
					transform: translateX(400px);
					opacity: 0;
				}
				to {
					transform: translateX(0);
					opacity: 1;
				}
			}
			@keyframes jooosiIconToastSlideOut {
				from {
					transform: translateX(0);
					opacity: 1;
				}
				to {
					transform: translateX(400px);
					opacity: 0;
				}
			}
		`;
		document.head.appendChild(style);
	}
	
	document.body.appendChild(toast);
	
	// Auto-remove after 3 seconds
	setTimeout(() => {
		toast.style.animation = 'jooosiIconToastSlideOut 0.3s ease-in';
		setTimeout(() => toast.remove(), 300);
	}, 3000);
};

// Function to copy icon HTML to clipboard
const copyIconToClipboard = async (iconName: string) => {
	const iconHTML = `<jooosi-icon name="${iconName}" width="32" height="32"></jooosi-icon>`;
	
	try {
		await navigator.clipboard.writeText(iconHTML);
		console.log('[Jooosi Icon] Copied to clipboard:', iconHTML);
		showToast('✓ Icon HTML copied to clipboard! Paste it into the editor.', 'success');
		return true;
	} catch (err) {
		console.error('[Jooosi Icon] Failed to copy to clipboard:', err);
		
		// Fallback: show a prompt with the HTML
		const copied = prompt('Copy this icon HTML:', iconHTML);
		if (copied !== null) {
			showToast('Icon HTML ready to paste!', 'success');
			return true;
		} else {
			showToast('Failed to copy icon HTML', 'error');
			return false;
		}
	}
};

// Function to create the Jooosi Icon button
const createJooosiIconButton = (): HTMLButtonElement => {
	// Create a new button element
	const button = document.createElement('button');
	button.setAttribute('id', 'jooosi-icon-element-button');
	button.setAttribute('aria-label', 'Add Jooosi Icon (copies HTML to clipboard)');
	button.setAttribute('class', 'element-button');
	button.setAttribute('type', 'button');
	
	// Add the icon SVG - using Jooosi Icon's logo
	button.innerHTML = `
		<div class="icon-wrapper">
			<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" class="etch-icon iconify iconify--jooosi-icon" width="16px" height="16px" viewBox="0 0 400 400">
				<path fill="currentColor" fill-rule="evenodd" d="M 0 0 H 400 V 400 H 0 Z M 195 61 L 158 99 L 400 333 L 400 259 Z M 0 75 L 0 149 L 195 339 L 232 301 Z" />
			</svg>
		</div>
	`;
	
	// Add click event listener to open icon picker and copy to clipboard
	button.addEventListener('click', (e) => {
		e.preventDefault();
		e.stopPropagation();
		
		const iconPicker = getIconPickerAPI();
		if (!iconPicker) {
			console.error('[Jooosi Icon] Icon picker API not available');
			showToast('Icon picker not available', 'error');
			return;
		}
		
		// Open the icon picker modal
		iconPicker.open('', async (iconName: string) => {
			if (iconName) {
				await copyIconToClipboard(iconName);
			}
		});
	});
	
	return button;
};

// Function to inject the button into the element menu
const injectElementMenuButton = () => {
	const elementMenu = document.querySelector('div.element-menu');
	
	if (!elementMenu) {
		console.warn('[Jooosi Icon] Element menu not found');
		return false;
	}
	
	// Check if button already exists
	const existingButton = elementMenu.querySelector('#jooosi-icon-element-button');
	if (existingButton) {
		// console.log('[Jooosi Icon] Element menu button already exists');
		return true;
	}
	
	// Create and append the button
	const button = createJooosiIconButton();
	elementMenu.appendChild(button);
	
	console.log('[Jooosi Icon] Element menu button injected successfully');
	return true;
};

// Set up observer to watch for element menu
const observer = new MutationObserver(() => {
	// Try to inject the button
	if (injectElementMenuButton()) {
		// If successful, we can disconnect the observer
		// But we'll keep it running in case the menu rebuilds
		// console.log('[Jooosi Icon] Element menu button ready');
	}
});

// Start observing
observer.observe(document.body, {
	subtree: true,
	childList: true,
});

// Try to inject immediately if the menu already exists
setTimeout(() => {
	injectElementMenuButton();
}, 500);

console.log('[Jooosi Icon] Element menu button module loaded');
