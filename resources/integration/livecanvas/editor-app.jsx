/**
 * Jooosi Icon Picker React App for LiveCanvas
 * 
 * This file handles the React rendering of the icon picker modal.
 */
import { createRoot } from '@wordpress/element';
import { useState, useEffect } from '@wordpress/element';
import IconPickerModal from './components/IconPickerModal';

// Global state for icon picker
let modalContainer = null;
let modalRoot = null;
let updateModalState = null;

/**
 * Modal State Manager Component
 * This component manages the modal state and passes it to the IconPickerModal
 */
function ModalStateManager() {
	const [modalState, setModalState] = useState({
		isOpen: false,
		currentIcon: '',
		callback: null,
	});

	// Expose state updater globally
	useEffect(() => {
		updateModalState = setModalState;
		return () => {
			updateModalState = null;
		};
	}, []);

	const handleClose = () => {
		setModalState(prev => ({
			...prev,
			isOpen: false,
		}));
	};

	const handleSelectIcon = (iconName) => {
		if (modalState.callback) {
			modalState.callback(iconName);
		}
		handleClose();
	};

	return (
		<IconPickerModal
			isOpen={modalState.isOpen}
			onClose={handleClose}
			onSelectIcon={handleSelectIcon}
			currentIcon={modalState.currentIcon}
		/>
	);
}

/**
 * Open the icon picker modal
 */
function openIconPicker(initialValue, callback) {

	if (updateModalState) {
		updateModalState({
			isOpen: true,
			currentIcon: initialValue || '',
			callback: callback,
		});
	} else {
		console.error('[Jooosi Icon] Modal state manager not initialized');
	}
}

/**
 * Close the icon picker modal
 */
function closeIconPicker() {
	if (updateModalState) {
		updateModalState(prev => ({
			...prev,
			isOpen: false,
		}));
	}
}

/**
 * Initialize and render the React modal
 */
function renderModal() {
	if (!modalContainer) {
		modalContainer = document.createElement('div');
		modalContainer.id = 'oilc-icon-picker-root';
		document.body.appendChild(modalContainer);
	}

	// Create root only once
	if (!modalRoot) {
		modalRoot = createRoot(modalContainer);
	}

	// Render the state manager component
	modalRoot.render(<ModalStateManager />);
}

/**
 * Cleanup function to unmount the modal
 */
function destroyModal() {
	if (modalRoot) {
		modalRoot.unmount();
		modalRoot = null;
	}
	if (modalContainer && modalContainer.parentNode) {
		modalContainer.parentNode.removeChild(modalContainer);
		modalContainer = null;
	}
	updateModalState = null;
}

// Export the API
export { openIconPicker, closeIconPicker, renderModal, destroyModal };
