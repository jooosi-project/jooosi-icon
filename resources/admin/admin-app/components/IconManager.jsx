import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { Button, Spinner, Notice, Modal, TextControl, FormFileUpload } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import IconTrash from '~icons/tabler/trash';
import IconRefresh from '~icons/tabler/refresh';
import IconSearch from '~icons/tabler/search';
import IconFolder from '~icons/tabler/folder';
import IconFolderPlus from '~icons/tabler/folder-plus';
import IconEdit from '~icons/tabler/edit';
import IconCheck from '~icons/tabler/check';
import IconX from '~icons/tabler/x';
import IconUpload from '~icons/tabler/upload';
import IconAlertCircle from '~icons/tabler/alert-circle';
import LocalIconItem from './LocalIconItem';

const IconManager = ({ refreshTrigger }) => {
	const [iconSets, setIconSets] = useState({});
	const [selectedSet, setSelectedSet] = useState('all');
	const [icons, setIcons] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);
	const [searchQuery, setSearchQuery] = useState('');
	const [selectedIcon, setSelectedIcon] = useState(null);
	const [deleteModal, setDeleteModal] = useState(null);
	const [isDeleting, setIsDeleting] = useState(false);
	const [draggedIcon, setDraggedIcon] = useState(null);
	const [selectedIcons, setSelectedIcons] = useState(new Set());
	const [lastSelectedIndex, setLastSelectedIndex] = useState(null);
	const [isSelecting, setIsSelecting] = useState(false);
	const [selectionStart, setSelectionStart] = useState(null);
	const [selectionBox, setSelectionBox] = useState(null);
	const [renameSet, setRenameSet] = useState(null); // { oldName: string, newName: string }
	const [isRenamingSet, setIsRenamingSet] = useState(false);
	const [createSetModal, setCreateSetModal] = useState(false);
	const [newSetName, setNewSetName] = useState('');
	const [isCreatingSet, setIsCreatingSet] = useState(false);
	const [uploadModal, setUploadModal] = useState(false);
	const [selectedFiles, setSelectedFiles] = useState([]);
	const [isUploading, setIsUploading] = useState(false);
	const [uploadStatus, setUploadStatus] = useState(null);
	const [uploadResult, setUploadResult] = useState(null);
	const [isDragOver, setIsDragOver] = useState(false);
	const fileInputRef = useRef(null);
	const gridRef = useRef(null);
	const dropZoneRef = useRef(null);

	// Fetch icon sets
	const fetchIconSets = useCallback(async () => {
		try {
			const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/sets`, {
				headers: {
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
			});

			if (!response.ok) {
				throw new Error(__('Failed to fetch icon sets', 'jooosi-icon'));
			}

			const data = await response.json();
			setIconSets(data.data || {});
		} catch (err) {
			setError(err.message);
		}
	}, []);

	// Fetch all icons or icons by set
	const fetchIcons = useCallback(async () => {
		setIsLoading(true);
		setError(null);

		try {
			let url = `${window.jooosiIconAdmin.apiUrl}/icons`;

			if (selectedSet !== 'all') {
				url = `${window.jooosiIconAdmin.apiUrl}/sets/${selectedSet}/icons`;
			}

			const response = await fetch(url, {
				headers: {
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
			});

			if (!response.ok) {
				throw new Error(__('Failed to fetch icons', 'jooosi-icon'));
			}

			const data = await response.json();
			setIcons(data.data || []);
		} catch (err) {
			setError(err.message);
		} finally {
			setIsLoading(false);
		}
	}, [selectedSet]);

	// Delete icon
	const handleDeleteIcon = useCallback(async (iconName) => {
		setIsDeleting(true);

		try {
			const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/${iconName}`, {
				method: 'DELETE',
				headers: {
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
			});

			if (!response.ok) {
				const data = await response.json();
				throw new Error(data.message || __('Failed to delete icon', 'jooosi-icon'));
			}

			// Refresh icons and sets
			await fetchIcons();
			await fetchIconSets();

			setDeleteModal(null);
			setSelectedIcon(null);
		} catch (err) {
			setError(err.message);
		} finally {
			setIsDeleting(false);
		}
	}, [fetchIcons, fetchIconSets]);

	// Bulk delete icons
	const handleBulkDelete = useCallback(async () => {
		if (selectedIcons.size === 0) return;

		setIsDeleting(true);
		const iconNames = Array.from(selectedIcons);
		const errors = [];

		try {
			// Delete icons sequentially
			for (const iconName of iconNames) {
				try {
					const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/${iconName}`, {
						method: 'DELETE',
						headers: {
							'X-WP-Nonce': window.jooosiIconAdmin.nonce,
						},
					});

					if (!response.ok) {
						const data = await response.json();
						errors.push(`${iconName}: ${data.message || 'Failed to delete'}`);
					}
				} catch (err) {
					errors.push(`${iconName}: ${err.message}`);
				}
			}

			// Refresh icons and sets
			await fetchIcons();
			await fetchIconSets();

			setDeleteModal(null);
			setSelectedIcons(new Set());

			if (errors.length > 0) {
				setError(__('Some icons failed to delete:\n', 'jooosi-icon') + errors.join('\n'));
			}
		} catch (err) {
			setError(err.message);
		} finally {
			setIsDeleting(false);
		}
	}, [selectedIcons, fetchIcons, fetchIconSets]);

	// Bulk move icons
	const handleBulkMove = useCallback(async (targetSet) => {
		if (selectedIcons.size === 0) return;

		const iconNames = Array.from(selectedIcons);
		const errors = [];

		try {
			// Move icons sequentially
			for (const iconName of iconNames) {
				try {
					const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/move`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.jooosiIconAdmin.nonce,
						},
						body: JSON.stringify({
							icon_name: iconName,
							target_set: targetSet === 'local' ? null : targetSet,
						}),
					});

					if (!response.ok) {
						const data = await response.json();
						errors.push(`${iconName}: ${data.message || 'Failed to move'}`);
					}
				} catch (err) {
					errors.push(`${iconName}: ${err.message}`);
				}
			}

			// Refresh icons and sets
			await fetchIcons();
			await fetchIconSets();
			setSelectedIcons(new Set());

			if (errors.length > 0) {
				setError(__('Some icons failed to move:\n', 'jooosi-icon') + errors.join('\n'));
			}
		} catch (err) {
			setError(err.message);
		}
	}, [selectedIcons, fetchIcons, fetchIconSets]);

	// Move icon to different set
	const handleMoveIcon = useCallback(async (iconName, targetSet) => {
		try {
			const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/move`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
				body: JSON.stringify({
					icon_name: iconName,
					target_set: targetSet === 'local' ? null : targetSet,
				}),
			});

			if (!response.ok) {
				const data = await response.json();
				throw new Error(data.message || __('Failed to move icon', 'jooosi-icon'));
			}

			// Refresh icons and sets
			await fetchIcons();
			await fetchIconSets();
			setSelectedIcon(null);
		} catch (err) {
			setError(err.message);
		}
	}, [fetchIcons, fetchIconSets]);

	// Rename icon set
	const handleRenameSet = useCallback(async () => {
		if (!renameSet || !renameSet.newName.trim()) {
			return;
		}

		setIsRenamingSet(true);

		try {
			const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/sets/${renameSet.oldName}/rename`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
				body: JSON.stringify({
					new_name: renameSet.newName.trim(),
				}),
			});

			if (!response.ok) {
				const data = await response.json();
				throw new Error(data.message || __('Failed to rename set', 'jooosi-icon'));
			}

			// If current selected set was renamed, update it
			if (selectedSet === renameSet.oldName) {
				setSelectedSet(renameSet.newName.trim());
			}

			// Refresh sets and icons
			await fetchIconSets();
			await fetchIcons();
			setRenameSet(null);
		} catch (err) {
			setError(err.message);
		} finally {
			setIsRenamingSet(false);
		}
	}, [renameSet, selectedSet, fetchIconSets, fetchIcons]);

	// Create new icon set
	const handleCreateSet = useCallback(async () => {
		if (!newSetName.trim()) {
			return;
		}

		setIsCreatingSet(true);

		try {
			const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/sets/create`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
				body: JSON.stringify({
					set_name: newSetName.trim(),
				}),
			});

			if (!response.ok) {
				const data = await response.json();
				throw new Error(data.message || __('Failed to create set', 'jooosi-icon'));
			}

			// Refresh sets
			await fetchIconSets();
			setCreateSetModal(false);
			setNewSetName('');
			setSelectedSet(newSetName.trim());
		} catch (err) {
			setError(err.message);
		} finally {
			setIsCreatingSet(false);
		}
	}, [newSetName, fetchIconSets]);

	// Validate and process files
	const validateFiles = useCallback((files) => {
		const validFiles = [];
		const errors = [];

		for (const file of files) {
			// Validate file type
			if (!file.name.toLowerCase().endsWith('.svg')) {
				errors.push(`${file.name}: ${__('Only SVG files are allowed', 'jooosi-icon')}`);
				continue;
			}

			// Validate file size (1MB max)
			if (file.size > 1024 * 1024) {
				errors.push(`${file.name}: ${__('File size must be less than 1MB', 'jooosi-icon')}`);
				continue;
			}

			validFiles.push(file);
		}

		return { validFiles, errors };
	}, []);

	// Upload icon(s) - file input
	const handleFileSelect = useCallback((event) => {
		const files = Array.from(event.target.files || []);

		if (files.length === 0) {
			setSelectedFiles([]);
			return;
		}

		const { validFiles, errors } = validateFiles(files);

		if (errors.length > 0) {
			setUploadStatus({
				type: 'error',
				message: errors.join('\n'),
			});
		} else {
			setUploadStatus(null);
		}

		setSelectedFiles(validFiles);
		setUploadResult(null);
	}, [validateFiles]);

	// Drag and drop handlers for file upload
	const handleUploadDragEnter = useCallback((e) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragOver(true);
	}, []);

	const handleUploadDragLeave = useCallback((e) => {
		e.preventDefault();
		e.stopPropagation();

		// Only set dragOver to false if we're leaving the drop zone entirely
		if (dropZoneRef.current && !dropZoneRef.current.contains(e.relatedTarget)) {
			setIsDragOver(false);
		}
	}, []);

	const handleUploadDragOver = useCallback((e) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragOver(true);
	}, []);

	const handleUploadDrop = useCallback((e) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragOver(false);

		const files = Array.from(e.dataTransfer.files || []);

		if (files.length === 0) {
			return;
		}

		const { validFiles, errors } = validateFiles(files);

		if (errors.length > 0) {
			setUploadStatus({
				type: 'error',
				message: errors.join('\n'),
			});
		} else {
			setUploadStatus(null);
		}

		setSelectedFiles(validFiles);
		setUploadResult(null);
	}, [validateFiles]);

	const handleUploadIcon = useCallback(async () => {
		if (selectedFiles.length === 0) {
			return;
		}

		setIsUploading(true);
		setUploadStatus(null);
		setUploadResult(null);

		try {
			const formData = new FormData();

			// Append all files with the same field name
			selectedFiles.forEach((file) => {
				formData.append('icon[]', file);
			});

			// Use selected set from grid (not from input field)
			const targetSet = selectedSet === 'all' ? '' : selectedSet;
			if (targetSet) {
				formData.append('icon_set', targetSet);
			}

			const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/upload`, {
				method: 'POST',
				headers: {
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
				body: formData,
			});

			const data = await response.json();

			if (!response.ok) {
				throw new Error(data.message || __('Upload failed', 'jooosi-icon'));
			}

			// Handle both single and multiple upload responses
			const isMultiple = data.uploaded_count !== undefined;

			if (isMultiple) {
				// Multiple file upload response
				const hasErrors = data.errors && data.errors.length > 0;

				setUploadStatus({
					type: hasErrors ? 'warning' : 'success',
					message: data.message,
				});

				setUploadResult({
					...data,
					isMultiple: true,
				});
			} else {
				// Single file upload response (backward compatibility)
				setUploadStatus({
					type: 'success',
					message: data.message || __('Icon uploaded successfully!', 'jooosi-icon'),
				});

				setUploadResult(data);
			}

			// Reset form
			setSelectedFiles([]);
			if (fileInputRef.current) {
				fileInputRef.current.value = '';
			}

			// Refresh icons and sets
			await fetchIcons();
			await fetchIconSets();

			// Auto close after 3 seconds on success (only if no errors)
			if (!isMultiple || (data.errors && data.errors.length === 0)) {
				setTimeout(() => {
					setUploadModal(false);
					setUploadStatus(null);
					setUploadResult(null);
				}, 3000);
			}

		} catch (error) {
			setUploadStatus({
				type: 'error',
				message: error.message || __('An error occurred during upload.', 'jooosi-icon'),
			});
		} finally {
			setIsUploading(false);
		}
	}, [selectedFiles, selectedSet, fetchIcons, fetchIconSets]);

	// Refresh data
	const handleRefresh = useCallback(async () => {
		try {
			// Clear cache first
			const response = await fetch(`${window.jooosiIconAdmin.apiUrl}/cache/clear`, {
				method: 'POST',
				headers: {
					'X-WP-Nonce': window.jooosiIconAdmin.nonce,
				},
			});
			if (!response.ok) {
				throw new Error(`Cache refresh failed: ${response.status}`);
			}

			const data = await response.json();
			if (typeof data.cache_key === 'string') {
				let cacheKeyMeta = document.head.querySelector('meta[name="jooosi-icon-cache-key"]');
				if (!cacheKeyMeta) {
					cacheKeyMeta = document.createElement('meta');
					cacheKeyMeta.name = 'jooosi-icon-cache-key';
					document.head.append(cacheKeyMeta);
				}
				cacheKeyMeta.content = data.cache_key;
				await window.IconRegistry?.refreshCache?.();
			}
		} catch (err) {
			// Silently fail cache clear, still continue with refresh
			console.warn('Failed to clear cache:', err);
		}

		await fetchIconSets();
		await fetchIcons();
	}, [fetchIconSets, fetchIcons]);

	// Clear selection
	const handleClearSelection = useCallback(() => {
		setSelectedIcons(new Set());
		setSelectedIcon(null);
		setLastSelectedIndex(null);
	}, []);

	// Drag and drop handlers
	const handleDragStart = useCallback((e, icon) => {
		// Check if this icon is part of multi-selection
		if (selectedIcons.has(icon.icon_name)) {
			// Dragging multiple icons
			setDraggedIcon({ isMultiple: true, icons: Array.from(selectedIcons) });
		} else {
			// Dragging single icon
			setDraggedIcon(icon);
		}
		e.dataTransfer.effectAllowed = 'move';
	}, [selectedIcons]);

	const handleDragEnd = useCallback(() => {
		setDraggedIcon(null);
	}, []);

	const handleDragOver = useCallback((e) => {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
	}, []);

	const handleDropOnSet = useCallback((e, targetSet) => {
		e.preventDefault();
		if (draggedIcon) {
			if (draggedIcon.isMultiple) {
				// Move multiple icons
				handleBulkMove(targetSet);
			} else if (draggedIcon.icon_set !== targetSet) {
				// Move single icon
				handleMoveIcon(draggedIcon.icon_name, targetSet);
			}
		}
		setDraggedIcon(null);
	}, [draggedIcon, handleMoveIcon, handleBulkMove]);

	// Check if a set is a valid drop target
	const isValidDropTarget = useCallback((targetSet) => {
		if (!draggedIcon) return false;

		if (draggedIcon.isMultiple) {
			return true; // Always allow dropping multiple icons
		}

		return draggedIcon.icon_set !== targetSet;
	}, [draggedIcon]);

	// Filter icons by search query
	const filteredIcons = useMemo(() => {
		if (!searchQuery.trim()) {
			return icons;
		}

		const query = searchQuery.toLowerCase();
		return icons.filter(icon =>
			icon.name.toLowerCase().includes(query) ||
			icon.icon_name.toLowerCase().includes(query)
		);
	}, [icons, searchQuery]);

	// Icon selection with Ctrl/Cmd and Shift support
	const handleSelectIcon = useCallback((iconName, index, event) => {
		const isCtrlClick = event?.ctrlKey || event?.metaKey;
		const isShiftClick = event?.shiftKey;

		if (isCtrlClick) {
			// Ctrl/Cmd+Click: Toggle individual selection
			setSelectedIcons(prev => {
				const newSet = new Set(prev);
				if (newSet.has(iconName)) {
					newSet.delete(iconName);
				} else {
					newSet.add(iconName);
				}
				return newSet;
			});
			setLastSelectedIndex(index);
			setSelectedIcon(null);
		} else if (isShiftClick && lastSelectedIndex !== null) {
			// Shift+Click: Range selection
			const start = Math.min(lastSelectedIndex, index);
			const end = Math.max(lastSelectedIndex, index);
			const rangeIcons = filteredIcons.slice(start, end + 1).map(icon => icon.icon_name);

			setSelectedIcons(prev => {
				const newSet = new Set(prev);
				rangeIcons.forEach(name => newSet.add(name));
				return newSet;
			});
			setSelectedIcon(null);
		} else if (selectedIcons.size > 0) {
			// If multi-selection exists, clicking without modifiers clears it
			setSelectedIcons(new Set());
			setSelectedIcon(iconName);
			setLastSelectedIndex(index);
		} else {
			// Single select mode
			setSelectedIcon(prevIcon => prevIcon === iconName ? null : iconName);
			setSelectedIcons(new Set());
			setLastSelectedIndex(index);
		}
	}, [lastSelectedIndex, filteredIcons, selectedIcons.size]);

	// Mouse area selection (marquee/lasso)
	const handleMouseDown = useCallback((e) => {
		// Only start selection on wrapper/grid background, not on icons or buttons
		const isOnBackground = e.target.classList.contains('jooosi-icon-grid') ||
		                        e.target.classList.contains('jooosi-icon-content-wrapper');

		if (isOnBackground && gridRef.current) {
			const rect = gridRef.current.getBoundingClientRect();
			setIsSelecting(true);
			setSelectionStart({
				x: e.clientX - rect.left,
				y: e.clientY - rect.top + gridRef.current.scrollTop,
			});
			setSelectionBox(null);

			// Clear selection if not holding Ctrl/Cmd
			if (!e.ctrlKey && !e.metaKey) {
				setSelectedIcons(new Set());
			}
		}
	}, []);

	const handleMouseMove = useCallback((e) => {
		if (!isSelecting || !selectionStart || !gridRef.current) return;

		const rect = gridRef.current.getBoundingClientRect();
		const currentX = e.clientX - rect.left;
		const currentY = e.clientY - rect.top + gridRef.current.scrollTop;

		const box = {
			left: Math.min(selectionStart.x, currentX),
			top: Math.min(selectionStart.y, currentY),
			width: Math.abs(currentX - selectionStart.x),
			height: Math.abs(currentY - selectionStart.y),
		};

		setSelectionBox(box);

		// Find icons within selection box
		const gridItems = gridRef.current.querySelectorAll('.jooosi-icon-item');
		const newSelectedIcons = new Set(e.ctrlKey || e.metaKey ? selectedIcons : []);

		gridItems.forEach((item) => {
			const itemRect = item.getBoundingClientRect();
			const itemRelativeRect = {
				left: itemRect.left - rect.left,
				top: itemRect.top - rect.top + gridRef.current.scrollTop,
				right: itemRect.right - rect.left,
				bottom: itemRect.bottom - rect.top + gridRef.current.scrollTop,
			};

			// Check if item intersects with selection box
			const intersects = !(
				itemRelativeRect.right < box.left ||
				itemRelativeRect.left > box.left + box.width ||
				itemRelativeRect.bottom < box.top ||
				itemRelativeRect.top > box.top + box.height
			);

			if (intersects) {
				const iconName = item.querySelector('.jooosi-icon-item-name')?.textContent;
				const iconData = filteredIcons.find(icon => icon.name === iconName);
				if (iconData) {
					newSelectedIcons.add(iconData.icon_name);
				}
			}
		});

		setSelectedIcons(newSelectedIcons);
	}, [isSelecting, selectionStart, selectedIcons, filteredIcons]);

	const handleMouseUp = useCallback(() => {
		setIsSelecting(false);
		setSelectionStart(null);
		setSelectionBox(null);
	}, []);

	// Add global mouse up listener
	useEffect(() => {
		if (isSelecting) {
			document.addEventListener('mouseup', handleMouseUp);
			return () => document.removeEventListener('mouseup', handleMouseUp);
		}
	}, [isSelecting, handleMouseUp]);

	// Keyboard shortcuts
	useEffect(() => {
		const handleKeyDown = (e) => {
			// Escape: Clear selection
			if (e.key === 'Escape' && selectedIcons.size > 0) {
				handleClearSelection();
			}

			// Delete/Backspace: Delete selected icons
			if ((e.key === 'Delete' || e.key === 'Backspace') && selectedIcons.size > 0) {
				// Don't trigger if user is typing in an input
				if (!['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
					e.preventDefault();
					setDeleteModal({ isMultiple: true, count: selectedIcons.size });
				}
			}

			// Ctrl/Cmd + A: Select all visible icons
			if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
				e.preventDefault();
				setSelectedIcons(new Set(filteredIcons.map(icon => icon.icon_name)));
				setSelectedIcon(null);
			}
		};

		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [selectedIcons.size, handleClearSelection, filteredIcons]);

	// Convert icons to IconItem format
	const iconItems = useMemo(() => {
		return filteredIcons.map(icon => ({
			name: icon.icon_name,
			iconName: icon.name,
			prefix: icon.icon_set || 'local',
		}));
	}, [filteredIcons]);

	// Get selected icon data
	const selectedIconData = useMemo(() => {
		return filteredIcons.find(icon => icon.icon_name === selectedIcon);
	}, [filteredIcons, selectedIcon]);

	// Initial load
	useEffect(() => {
		fetchIconSets();
		fetchIcons();
	}, [fetchIconSets, fetchIcons]);

	// Refresh when upload succeeds
	useEffect(() => {
		if (refreshTrigger > 0) {
			handleRefresh();
		}
	}, [refreshTrigger, handleRefresh]);

	// Calculate total count
	const totalCount = useMemo(() => {
		return Object.values(iconSets).reduce((sum, set) => sum + set.total, 0);
	}, [iconSets]);

	// Get target set display name for upload modal
	const targetSetDisplayName = useMemo(() => {
		if (selectedSet === 'all') {
			return __('Local (default)', 'jooosi-icon');
		}
		return iconSets[selectedSet]?.name || selectedSet;
	}, [selectedSet, iconSets]);

	return (
		<div className="jooosi-icon-manager">
			<div className="jooosi-icon-manager-header">
				<div className="header-info">
					<h2>{__('Manage Local Icons', 'jooosi-icon')}</h2>
					<p className="description">
						{totalCount > 0
							? __(`You have ${totalCount} custom icons across ${Object.keys(iconSets).length} sets.`, 'jooosi-icon')
							: __('No custom icons uploaded yet.', 'jooosi-icon')
						}
					</p>
				</div>
				<div className="header-actions">
					<Button
						variant="primary"
						onClick={() => setUploadModal(true)}
						icon={<IconUpload />}
					>
						{__('Upload Icons', 'jooosi-icon')}
					</Button>
					<Button
						variant="secondary"
						onClick={handleRefresh}
						disabled={isLoading}
						icon={<IconRefresh />}
					>
						{__('Refresh', 'jooosi-icon')}
					</Button>
				</div>
			</div>

			{error && (
				<Notice status="error" onRemove={() => setError(null)} isDismissible>
					{error}
				</Notice>
			)}

			{/* Icon Set Grid */}
			<div className="jooosi-icon-set-grid-wrapper">
				<div className="set-grid-label">
					<IconFolder />
					<span>{__('Icon Sets', 'jooosi-icon')}</span>
				</div>
				<div className="jooosi-icon-set-grid">
					{/* All Icons Set */}
					<button
						className={`set-card ${selectedSet === 'all' ? 'is-active' : ''}`}
						onClick={() => setSelectedSet('all')}
					>
						<div className="set-card-icon">
							<IconFolder />
						</div>
						<div className="set-card-info">
							<span className="set-card-name">{__('All Icons', 'jooosi-icon')}</span>
							<span className="set-card-count">{totalCount} {__('icons', 'jooosi-icon')}</span>
						</div>
					</button>

					{/* Existing Sets */}
					{Object.entries(iconSets).map(([prefix, set]) => (
						<button
							key={prefix}
							className={`set-card ${selectedSet === prefix ? 'is-active' : ''} ${draggedIcon && isValidDropTarget(prefix) ? 'is-drop-target' : ''}`}
							onClick={() => setSelectedSet(prefix)}
							onDragOver={handleDragOver}
							onDrop={(e) => handleDropOnSet(e, prefix)}
						>
							<div className="set-card-icon">
								<IconFolder />
							</div>
							<div className="set-card-info">
								<span className="set-card-name">{set.name}</span>
								<span className="set-card-count">{set.total} {__('icons', 'jooosi-icon')}</span>
							</div>
							{prefix !== 'local' && (
								<button
									className="set-card-rename"
									onClick={(e) => {
										e.stopPropagation();
										setRenameSet({ oldName: prefix, newName: prefix });
									}}
									title={__('Rename set', 'jooosi-icon')}
								>
									<IconEdit />
								</button>
							)}
						</button>
					))}

					{/* Create New Set Card */}
					<button
						className="set-card set-card-create"
						onClick={() => setCreateSetModal(true)}
					>
						<div className="set-card-icon">
							<IconFolderPlus />
						</div>
						<div className="set-card-info">
							<span className="set-card-name">{__('Create New Set', 'jooosi-icon')}</span>
						</div>
					</button>
				</div>
			</div>

			{/* Search */}
			{icons.length > 0 && (
				<div className="jooosi-icon-search">
					<div className="jooosi-search-wrapper">
						<IconSearch className="jooosi-search-icon" />
						<input
							type="text"
							className="jooosi-search-input"
							value={searchQuery}
							onChange={(e) => setSearchQuery(e.target.value)}
							placeholder={__('Search icons...', 'jooosi-icon')}
						/>
						{searchQuery && (
							<button
								className="jooosi-search-clear"
								onClick={() => setSearchQuery('')}
								aria-label={__('Clear search', 'jooosi-icon')}
							>
								<IconX />
							</button>
						)}
					</div>
				</div>
			)}

			{/* Icons Grid */}
			<div
				className="jooosi-icon-content-wrapper"
				onMouseDown={handleMouseDown}
				onMouseMove={handleMouseMove}
				style={{ position: 'relative', userSelect: 'none' }}
			>
				{isLoading ? (
					<div className="jooosi-icon-loading">
						<Spinner />
						<p>{__('Loading icons...', 'jooosi-icon')}</p>
					</div>
				) : iconItems.length > 0 ? (
					<div
						className="jooosi-icon-grid"
						ref={gridRef}
					>
						{iconItems.map((icon, index) => (
							<LocalIconItem
								key={icon.name}
								icon={icon}
								isSelected={selectedIcons.has(icon.name) || icon.name === selectedIcon}
								onSelect={handleSelectIcon}
								onDragStart={handleDragStart}
								onDragEnd={handleDragEnd}
								index={index}
							/>
						))}
						{/* Selection Box Overlay */}
						{selectionBox && (
							<div
								className="jooosi-icon-selection-box"
								style={{
									position: 'absolute',
									left: `${selectionBox.left}px`,
									top: `${selectionBox.top}px`,
									width: `${selectionBox.width}px`,
									height: `${selectionBox.height}px`,
									pointerEvents: 'none',
								}}
							/>
						)}
					</div>
				) : (
					<div className="jooosi-icon-empty">
						<IconSearch style={{ width: '64px', height: '64px', opacity: 0.3 }} />
						<h3>
							{searchQuery
								? __('No icons found', 'jooosi-icon')
								: __('No icons uploaded', 'jooosi-icon')
							}
						</h3>
						<p>
							{searchQuery
								? __('Try a different search term', 'jooosi-icon')
								: __('Upload your first icon using the "Upload Icons" tab', 'jooosi-icon')
							}
						</p>
					</div>
				)}
			</div>

			{/* Floating Action Bar - shown when multiple icons are selected */}
			{selectedIcons.size > 0 && (
				<div className="jooosi-icon-floating-action-bar">
					<div className="floating-bar-content">
						<div className="selection-info">
							<span className="selection-count-badge">{selectedIcons.size}</span>
							<span className="selection-text">
								{selectedIcons.size === 1
									? __('1 icon selected', 'jooosi-icon')
									: __(`${selectedIcons.size} icons selected`, 'jooosi-icon')
								}
							</span>
						</div>
						<div className="action-buttons">
							<Button
								variant="secondary"
								onClick={handleClearSelection}
								size="compact"
							>
								{__('Clear Selection', 'jooosi-icon')}
							</Button>
							<Button
								variant="secondary"
								onClick={() => setDeleteModal({ isMultiple: true, count: selectedIcons.size })}
								icon={<IconTrash />}
								isDestructive
								size="compact"
							>
								{__('Delete', 'jooosi-icon')} ({selectedIcons.size})
							</Button>
						</div>
					</div>
				</div>
			)}

			{/* Action Footer - shown when single icon is selected */}
			{selectedIcon && selectedIconData && !selectedIcons.size && (
				<div className="jooosi-icon-action-footer">
					<div className="selected-icon-preview">
						<jooosi-icon
							name={selectedIcon}
							width="32"
							height="32"
						/>
						<div className="selected-icon-info">
							<span className="selected-icon-label">{__('Selected:', 'jooosi-icon')}</span>
							<span className="selected-icon-name">{selectedIcon}</span>
						</div>
					</div>
					<div className="action-buttons">
						<Button
							variant="secondary"
							onClick={() => setSelectedIcon(null)}
						>
							{__('Cancel', 'jooosi-icon')}
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={() => setDeleteModal({ iconName: selectedIcon, iconData: selectedIconData })}
							icon={<IconTrash />}
						>
							{__('Delete Icon', 'jooosi-icon')}
						</Button>
					</div>
				</div>
			)}

			{/* Delete Confirmation Modal */}
			{deleteModal && (
				<Modal
					title={deleteModal.isMultiple
						? __('Delete Multiple Icons', 'jooosi-icon')
						: __('Delete Icon', 'jooosi-icon')
					}
					onRequestClose={() => setDeleteModal(null)}
					className="jooosi-icon-delete-modal"
				>
					<div className="delete-modal-content">
						{deleteModal.isMultiple ? (
							<>
								<p>
									{__(`Are you sure you want to delete ${deleteModal.count} icons?`, 'jooosi-icon')}
								</p>
								<div className="delete-preview-grid">
									{Array.from(selectedIcons).slice(0, 6).map(iconName => {
										const iconData = filteredIcons.find(i => i.icon_name === iconName);
										return (
											<div key={iconName} className="delete-preview-item">
												<jooosi-icon
													name={iconName}
													width="32"
													height="32"
												/>
												<span>{iconData?.name || iconName}</span>
											</div>
										);
									})}
									{selectedIcons.size > 6 && (
										<div className="delete-preview-more">
											+{selectedIcons.size - 6} {__('more', 'jooosi-icon')}
										</div>
									)}
								</div>
							</>
						) : (
							<>
								<p>{__('Are you sure you want to delete this icon?', 'jooosi-icon')}</p>
								<div className="delete-preview">
									<jooosi-icon
										name={deleteModal.iconName}
										width="64"
										height="64"
									/>
									<div>
										<strong>{deleteModal.iconData.name}</strong>
										<br />
										<code>{deleteModal.iconName}</code>
									</div>
								</div>
							</>
						)}
						<p className="warning">
							{__('This action cannot be undone.', 'jooosi-icon')}
						</p>
					</div>
					<div className="delete-modal-actions">
						<Button
							variant="secondary"
							onClick={() => setDeleteModal(null)}
							disabled={isDeleting}
						>
							{__('Cancel', 'jooosi-icon')}
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={() => deleteModal.isMultiple ? handleBulkDelete() : handleDeleteIcon(deleteModal.iconName)}
							isBusy={isDeleting}
							disabled={isDeleting}
						>
							{isDeleting
								? __('Deleting...', 'jooosi-icon')
								: deleteModal.isMultiple
									? __(`Delete ${deleteModal.count} Icons`, 'jooosi-icon')
									: __('Delete', 'jooosi-icon')
							}
						</Button>
					</div>
				</Modal>
			)}

			{/* Rename Set Modal */}
			{renameSet && (
				<Modal
					title={__('Rename Icon Set', 'jooosi-icon')}
					onRequestClose={() => setRenameSet(null)}
					className="jooosi-icon-rename-modal"
				>
					<div className="rename-modal-content">
						<TextControl
							label={__('Set Name', 'jooosi-icon')}
							value={renameSet.newName}
							onChange={(value) => setRenameSet({ ...renameSet, newName: value })}
							placeholder={__('Enter new set name', 'jooosi-icon')}
							help={__('Use lowercase letters, numbers, and hyphens only.', 'jooosi-icon')}
						/>
					</div>
					<div className="rename-modal-actions">
						<Button
							variant="secondary"
							onClick={() => setRenameSet(null)}
							disabled={isRenamingSet}
						>
							{__('Cancel', 'jooosi-icon')}
						</Button>
						<Button
							variant="primary"
							onClick={handleRenameSet}
							isBusy={isRenamingSet}
							disabled={isRenamingSet || !renameSet.newName.trim() || renameSet.newName === renameSet.oldName}
							icon={<IconCheck />}
						>
							{isRenamingSet ? __('Renaming...', 'jooosi-icon') : __('Rename', 'jooosi-icon')}
						</Button>
					</div>
				</Modal>
			)}

			{/* Create Set Modal */}
			{createSetModal && (
				<Modal
					title={__('Create New Icon Set', 'jooosi-icon')}
					onRequestClose={() => {
						setCreateSetModal(false);
						setNewSetName('');
					}}
					className="jooosi-icon-create-set-modal"
				>
					<div className="create-set-modal-content">
						<TextControl
							label={__('Set Name', 'jooosi-icon')}
							value={newSetName}
							onChange={setNewSetName}
							placeholder={__('e.g., brand, social, custom', 'jooosi-icon')}
							help={__('Use lowercase letters, numbers, and hyphens only. This will be used as the icon prefix.', 'jooosi-icon')}
							autoFocus
						/>
					</div>
					<div className="create-set-modal-actions">
						<Button
							variant="secondary"
							onClick={() => {
								setCreateSetModal(false);
								setNewSetName('');
							}}
							disabled={isCreatingSet}
						>
							{__('Cancel', 'jooosi-icon')}
						</Button>
						<Button
							variant="primary"
							onClick={handleCreateSet}
							isBusy={isCreatingSet}
							disabled={isCreatingSet || !newSetName.trim()}
							icon={<IconFolderPlus />}
						>
							{isCreatingSet ? __('Creating...', 'jooosi-icon') : __('Create Set', 'jooosi-icon')}
						</Button>
					</div>
				</Modal>
			)}

			{/* Upload Icon Modal */}
			{uploadModal && (
				<Modal
					title={__('Upload Icons', 'jooosi-icon')}
					onRequestClose={() => {
						setUploadModal(false);
						setSelectedFiles([]);
						setUploadStatus(null);
						setUploadResult(null);
						setIsDragOver(false);
					}}
					className="jooosi-icon-upload-modal"
				>
					<div
						className={`upload-modal-content ${isDragOver ? 'is-drag-over' : ''}`}
						ref={dropZoneRef}
						onDragEnter={handleUploadDragEnter}
						onDragOver={handleUploadDragOver}
						onDragLeave={handleUploadDragLeave}
						onDrop={handleUploadDrop}
					>
						{isDragOver && (
							<div className="drag-overlay">
								<IconUpload className="drag-overlay-icon" />
								<p className="drag-overlay-text">{__('Drop files to upload', 'jooosi-icon')}</p>
							</div>
						)}

						<div className="upload-form-field">
							<FormFileUpload
								accept=".svg,image/svg+xml"
								multiple
								onChange={handleFileSelect}
								ref={fileInputRef}
								render={({ openFileDialog }) => (
									<Button
										variant="secondary"
										onClick={openFileDialog}
										icon={<IconUpload />}
										className="upload-file-button"
									>
										{selectedFiles.length > 0
											? __(`${selectedFiles.length} file(s) selected`, 'jooosi-icon')
											: __('Choose SVG File(s)', 'jooosi-icon')
										}
									</Button>
								)}
							/>
							<p className="upload-hint">
								{__('or drag and drop files here', 'jooosi-icon')}
							</p>
							{selectedFiles.length > 0 && (
								<div className="files-info">
									{selectedFiles.map((file, index) => (
										<div key={index} className="file-info">
											<IconCheck className="icon-success" />
											<span>{file.name}</span>
											<span className="file-size">
												({(file.size / 1024).toFixed(2)} KB)
											</span>
										</div>
									))}
								</div>
							)}
						</div>

						<div className="target-set-info">
							<label className="target-set-label">
								<IconFolder />
								<span>{__('Upload to:', 'jooosi-icon')}</span>
							</label>
							<div className="target-set-display">
								<strong>{targetSetDisplayName}</strong>
							</div>
							<p className="target-set-help">
								{__('Select a different icon set from the grid above to change the upload destination.', 'jooosi-icon')}
							</p>
						</div>

						{uploadStatus && (
							<Notice
								status={uploadStatus.type}
								onRemove={() => setUploadStatus(null)}
								isDismissible
							>
								<div style={{ whiteSpace: 'pre-line' }}>{uploadStatus.message}</div>
							</Notice>
						)}

						{uploadResult && (
							<div className="upload-result">
								{uploadResult.isMultiple ? (
									<>
										{/* Multiple upload results */}
										{uploadResult.results && uploadResult.results.length > 0 && (
											<div className="upload-success-list">
												<h4>{__('Successfully uploaded:', 'jooosi-icon')}</h4>
												{uploadResult.results.map((result, index) => (
													<div key={index} className="result-item">
														<div className="result-preview">
															<jooosi-icon
																name={result.icon_name}
																width="32"
																height="32"
															/>
														</div>
														<div className="result-info">
															<strong>{result.filename}</strong>
															<br />
															<code>{result.icon_name}</code>
														</div>
													</div>
												))}
											</div>
										)}
										{uploadResult.errors && uploadResult.errors.length > 0 && (
											<div className="upload-error-list">
												<h4>{__('Failed uploads:', 'jooosi-icon')}</h4>
												{uploadResult.errors.map((error, index) => (
													<div key={index} className="error-item">
														<IconX className="icon-error" />
														<div>
															<strong>{error.filename}</strong>
															<br />
															<span>{error.message}</span>
														</div>
													</div>
												))}
											</div>
										)}
									</>
								) : uploadResult.icon_name ? (
									<>
										{/* Single upload result */}
										<div className="result-preview">
											<jooosi-icon
												name={uploadResult.icon_name}
												width="48"
												height="48"
											/>
										</div>
										<div className="result-info">
											<div className="info-row">
												<strong>{__('Icon Name:', 'jooosi-icon')}</strong>
												<code>{uploadResult.icon_name}</code>
											</div>
										</div>
									</>
								) : null}
							</div>
						)}

						<div className="upload-guidelines">
							<h4>{__('Guidelines', 'jooosi-icon')}</h4>
							<ul>
								<li><IconAlertCircle /> {__('Only SVG files are accepted', 'jooosi-icon')}</li>
								<li><IconAlertCircle /> {__('Maximum file size: 1MB per file', 'jooosi-icon')}</li>
								<li><IconAlertCircle /> {__('Files will be automatically sanitized', 'jooosi-icon')}</li>
								<li><IconAlertCircle /> {__('You can select multiple files at once', 'jooosi-icon')}</li>
							</ul>
						</div>
					</div>
					<div className="upload-modal-actions">
						<Button
							variant="secondary"
							onClick={() => {
								setUploadModal(false);
								setSelectedFiles([]);
								setUploadStatus(null);
								setUploadResult(null);
							}}
							disabled={isUploading}
						>
							{__('Close', 'jooosi-icon')}
						</Button>
						<Button
							variant="primary"
							onClick={handleUploadIcon}
							isBusy={isUploading}
							disabled={selectedFiles.length === 0 || isUploading}
							icon={<IconUpload />}
						>
							{isUploading
								? __('Uploading...', 'jooosi-icon')
								: selectedFiles.length > 1
									? __(`Upload ${selectedFiles.length} Icons`, 'jooosi-icon')
									: __('Upload Icon', 'jooosi-icon')
							}
						</Button>
					</div>
				</Modal>
			)}
		</div>
	);
};

export default IconManager;
