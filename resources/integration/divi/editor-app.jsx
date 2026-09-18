import { createRoot, createElement, useEffect, useState } from '@wordpress/element';
import IconPickerModal from './icon-picker-modal';

let modalContainer = null;
let modalRoot = null;
let updateModalState = null;

function ModalStateManager() {
	const [modalState, setModalState] = useState({
		isOpen: false,
		currentIcon: '',
		callback: null,
	});

	useEffect(() => {
		updateModalState = setModalState;

		return () => {
			updateModalState = null;
		};
	}, []);

	const handleClose = () => {
		setModalState((previous) => ({
			...previous,
			isOpen: false,
		}));
	};

	const handleSelectIcon = (iconName) => {
		if (modalState.callback) {
			modalState.callback(iconName);
		}

		handleClose();
	};

	return createElement(IconPickerModal, {
		isOpen: modalState.isOpen,
		onClose: handleClose,
		onSelectIcon: handleSelectIcon,
		currentIcon: modalState.currentIcon,
	});
}

function openIconPicker(initialValue, callback) {
	if (updateModalState) {
		updateModalState({
			isOpen: true,
			currentIcon: initialValue || '',
			callback,
		});
	} else {
		console.error('[Jooosi Icon] Divi picker modal state manager is not initialized');
	}
}

function closeIconPicker() {
	if (updateModalState) {
		updateModalState((previous) => ({
			...previous,
			isOpen: false,
		}));
	}
}

function renderModal() {
	if (!modalContainer) {
		modalContainer = document.createElement('div');
		modalContainer.id = 'oidiv-icon-picker-root';
		document.body.appendChild(modalContainer);
	}

	if (!modalRoot) {
		modalRoot = createRoot(modalContainer);
	}

	modalRoot.render(createElement(ModalStateManager));
}

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

export { closeIconPicker, destroyModal, openIconPicker, renderModal };
