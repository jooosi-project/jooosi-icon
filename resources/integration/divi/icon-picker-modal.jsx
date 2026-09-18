import { Modal } from '@wordpress/components';
import { createElement, useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import IconSearch from '~icons/tabler/search';
import IconX from '~icons/tabler/x';
import IconRefresh from '~icons/tabler/refresh';
import {
	useDebounce,
	useIconCollections,
	useIconFiltering,
	useIconSearch,
	useDefaultIcons,
} from './icon-picker-hooks';

const ICONS_PER_PAGE = 64;

const renderIconItem = (icon, isSelected, onSelect, index) => createElement(
	'button',
	{
		type: 'button',
		key: icon.name,
		className: `oiib-icon-item ${isSelected ? 'is-selected' : ''}`,
		onClick: () => onSelect(icon.name),
		title: icon.name,
		'data-index': index,
		'aria-label': `Select icon ${icon.name}`,
		'aria-pressed': isSelected,
	},
	createElement(
		'div',
		{ className: 'oiib-icon-preview' },
		createElement('jooosi-icon', {
			name: icon.name,
			width: '32',
			height: '32',
		})
	),
	createElement(
		'div',
		{ className: 'oiib-icon-label' },
		createElement('span', { className: 'oiib-icon-name' }, icon.iconName),
		createElement('span', { className: 'oiib-icon-prefix' }, icon.prefix)
	)
);

const IconPickerModal = ({ isOpen, onClose, onSelectIcon, currentIcon }) => {
	const [searchQuery, setSearchQuery] = useState('');
	const debouncedSearchQuery = useDebounce(searchQuery, 300);
	const [selectedCollection, setSelectedCollection] = useState('all');
	const [tempSelectedIcon, setTempSelectedIcon] = useState(currentIcon || null);
	const searchInputRef = useRef(null);

	const { collections, isLoading: isLoadingCollections, isRefreshing, error: collectionsError, refreshCollections } = useIconCollections(isOpen);
	const { icons: searchResults, isLoading: isSearching, error: searchError } = useIconSearch(debouncedSearchQuery, isOpen);
	const defaultIcons = useDefaultIcons(collections, currentIcon);

	const allIcons = useMemo(() => (
		debouncedSearchQuery.trim() ? searchResults : defaultIcons
	), [debouncedSearchQuery, searchResults, defaultIcons]);

	const {
		paginatedIcons,
		totalPages,
		currentPage,
		setCurrentPage,
		collectionCounts,
	} = useIconFiltering(allIcons, selectedCollection, ICONS_PER_PAGE);

	const isLoading = isLoadingCollections || isSearching;
	const error = collectionsError || searchError;

	const handleSelectIcon = useCallback((iconName) => {
		setTempSelectedIcon((previousIcon) => (previousIcon === iconName ? null : iconName));
	}, []);

	const handleConfirmSelection = useCallback(() => {
		onSelectIcon(tempSelectedIcon || '');
		onClose();
	}, [onClose, onSelectIcon, tempSelectedIcon]);

	const handleCancelSelection = useCallback(() => {
		onClose();
	}, [onClose]);

	const handlePageChange = useCallback((newPage) => {
		if (newPage >= 1 && newPage <= totalPages) {
			setCurrentPage(newPage);
		}
	}, [setCurrentPage, totalPages]);

	const totalCount = useMemo(() => {
		if (selectedCollection === 'all') {
			return allIcons.length;
		}

		return allIcons.filter((icon) => icon.prefix === selectedCollection).length;
	}, [allIcons, selectedCollection]);

	useEffect(() => {
		if (isOpen) {
			setTempSelectedIcon(currentIcon || null);
			document.body.classList.add('oiel-modal-open');
			setTimeout(() => {
				if (searchInputRef.current) {
					searchInputRef.current.focus();
				}
			}, 100);
		} else {
			const timeout = setTimeout(() => {
				setSearchQuery('');
				setSelectedCollection('all');
				setTempSelectedIcon(null);
			}, 200);

			document.body.classList.remove('oiel-modal-open');
			return () => clearTimeout(timeout);
		}

		return undefined;
	}, [currentIcon, isOpen]);

	useEffect(() => {
		const handleEscape = (event) => {
			if (event.key === 'Escape' && isOpen) {
				event.preventDefault();
				event.stopPropagation();
				onClose();
			}
		};

		if (isOpen) {
			document.addEventListener('keydown', handleEscape, true);
			return () => {
				document.removeEventListener('keydown', handleEscape, true);
			};
		}

		return undefined;
	}, [isOpen, onClose]);

	const infoMessage = useMemo(() => {
		if (isLoading) {
			if (searchQuery) {
				const collectionName = selectedCollection === 'all' ? '' : ` in ${collections[selectedCollection]?.name || selectedCollection}`;
				return __(`Searching for "${searchQuery}"${collectionName}...`, 'jooosi-icon');
			}

			return __('Loading icons...', 'jooosi-icon');
		}

		if (debouncedSearchQuery) {
			const collectionName = selectedCollection === 'all' ? '' : ` in ${collections[selectedCollection]?.name || selectedCollection}`;

			return totalCount > 0
				? __(`Found ${totalCount} icons for "${debouncedSearchQuery}"${collectionName}`, 'jooosi-icon')
				: __(`No icons found for "${debouncedSearchQuery}"${collectionName}`, 'jooosi-icon');
		}

		return selectedCollection === 'all'
			? __('Showing sample icons from all collections', 'jooosi-icon')
			: __(`Showing sample icons from ${collections[selectedCollection]?.name || selectedCollection}`, 'jooosi-icon');
	}, [collections, debouncedSearchQuery, isLoading, searchQuery, selectedCollection, totalCount]);

	return createElement(
		'fragment',
		null,
		isOpen ? createElement(
			Modal,
			{
				title: __('Jooosi Icon - Icon Picker', 'jooosi-icon'),
				onRequestClose: onClose,
				className: 'oiib-icon-picker-modal oiel-icon-picker-modal',
				size: 'large',
				'aria-label': __('Icon Picker', 'jooosi-icon'),
			},
			createElement(
				'div',
				{ className: 'oiib-icon-picker-content' },
				createElement(
					'div',
					{ className: 'oiib-icon-picker-search' },
					createElement(
						'div',
						{ className: 'oiib-search-wrapper' },
						createElement(IconSearch, { className: 'oiib-search-icon', 'aria-hidden': 'true' }),
						createElement('input', {
							ref: searchInputRef,
							type: 'text',
							className: 'oiib-search-input',
							value: searchQuery,
							onChange: (event) => setSearchQuery(event.target.value),
							placeholder: __('Search icons... (e.g., home, mdi:heart, lucide:star)', 'jooosi-icon'),
							'aria-label': __('Search icons', 'jooosi-icon'),
						}),
						searchQuery ? createElement(
							'button',
							{
								type: 'button',
								className: 'oiib-search-clear',
								onClick: () => setSearchQuery(''),
								'aria-label': __('Clear search', 'jooosi-icon'),
							},
							createElement(IconX, { 'aria-hidden': 'true' })
						) : null
					),
					createElement(
						'button',
						{
							type: 'button',
							className: 'oiib-search-refresh',
							onClick: refreshCollections,
								disabled: isRefreshing,
								'aria-label': __('Refresh icon collections', 'jooosi-icon'),
								title: __('Refresh icon collections', 'jooosi-icon'),
							},
							createElement(IconRefresh, { className: isRefreshing ? 'is-spinning' : '', 'aria-hidden': 'true' })
						)
					),
				Object.keys(collections).length > 0 ? createElement(
					'div',
					{ className: 'oiib-collection-filter', role: 'tablist', 'aria-label': __('Filter by collection', 'jooosi-icon') },
					createElement(
						'div',
						{ className: 'oiib-collection-filter-wrapper' },
						createElement(
							'button',
							{
								type: 'button',
								className: `oiib-collection-chip ${selectedCollection === 'all' ? 'is-active' : ''}`,
								onClick: () => setSelectedCollection('all'),
								role: 'tab',
								'aria-selected': selectedCollection === 'all',
							},
							__('All', 'jooosi-icon'),
							createElement('span', { className: 'oiib-collection-count' }, allIcons.length)
						),
						...Object.entries(collections).map(([prefix, collection]) => {
							const count = collectionCounts[prefix] || 0;
							if (debouncedSearchQuery && count === 0) {
								return null;
							}

							return createElement(
								'button',
								{
									type: 'button',
									key: prefix,
									className: `oiib-collection-chip ${selectedCollection === prefix ? 'is-active' : ''}`,
									onClick: () => setSelectedCollection(prefix),
									title: collection.name,
									'data-count': count,
									role: 'tab',
									'aria-selected': selectedCollection === prefix,
								},
								collection.name || prefix,
								createElement('span', { className: 'oiib-collection-count' }, count)
							);
						})
					)
				) : null,
				createElement(
					'div',
					{ className: 'oiib-icon-picker-info' },
					createElement(
						'div',
						{ className: 'oiib-info-text' },
						createElement('p', null, infoMessage)
					),
					!isLoading && debouncedSearchQuery && totalPages > 1 ? createElement(
						'div',
						{ className: 'oiib-pagination', role: 'navigation', 'aria-label': __('Icon pagination', 'jooosi-icon') },
						createElement(
							'button',
							{
								type: 'button',
								className: 'oiib-pagination-btn',
								onClick: () => handlePageChange(currentPage - 1),
								disabled: currentPage === 1,
								'aria-label': __('Previous page', 'jooosi-icon'),
							},
							'Prev'
						),
						createElement('span', { className: 'oiib-pagination-info' }, __(`Page ${currentPage} of ${totalPages}`, 'jooosi-icon')),
						createElement(
							'button',
							{
								type: 'button',
								className: 'oiib-pagination-btn',
								onClick: () => handlePageChange(currentPage + 1),
								disabled: currentPage === totalPages,
								'aria-label': __('Next page', 'jooosi-icon'),
							},
							'Next'
						)
					) : null
				),
				createElement(
					'div',
					{ className: 'oiib-icon-picker-content-wrapper' },
					isLoading ? createElement(
						'div',
						{ className: 'oiib-icon-picker-loading', role: 'status', 'aria-live': 'polite' },
						createElement('p', null, __('Loading icons...', 'jooosi-icon'))
					) : null,
					error ? createElement(
						'div',
						{ className: 'oiib-icon-picker-error', role: 'alert' },
						createElement('p', null, error)
					) : null,
					!isLoading && !error && paginatedIcons.length > 0 ? createElement(
						'div',
						{ className: 'oiib-icon-picker-grid', role: 'grid', 'aria-label': __('Icon grid', 'jooosi-icon') },
						...paginatedIcons.map((icon, index) => renderIconItem(icon, icon.name === tempSelectedIcon, handleSelectIcon, index))
					) : null,
					!isLoading && !error && paginatedIcons.length === 0 && debouncedSearchQuery ? createElement(
						'div',
						{ className: 'oiib-icon-picker-empty', role: 'status' },
						createElement('h3', null, __('No icons found', 'jooosi-icon')),
						createElement('p', null, __('Try a different search term or icon prefix', 'jooosi-icon'))
					) : null
				),
				(tempSelectedIcon !== currentIcon || tempSelectedIcon || currentIcon) ? createElement(
					'div',
					{ className: 'oiib-icon-picker-footer' },
					createElement(
						'div',
						{ className: 'oiib-selected-icon-preview' },
						tempSelectedIcon ? createElement(
							'div',
							{ className: 'oiib-selected-icon-display' },
							createElement('jooosi-icon', { name: tempSelectedIcon, width: '32', height: '32' }),
							createElement(
								'div',
								{ className: 'oiib-selected-icon-info' },
								createElement('span', { className: 'oiib-selected-icon-label' }, __('Selected:', 'jooosi-icon')),
								createElement('span', { className: 'oiib-selected-icon-name' }, tempSelectedIcon)
							)
						) : createElement(
							'div',
							{ className: 'oiib-selected-icon-display' },
							createElement(
								'div',
								{ className: 'oiib-selected-icon-info' },
								createElement('span', { className: 'oiib-selected-icon-label' }, __('Icon will be removed', 'jooosi-icon'))
							)
						),
						createElement(
							'div',
							{ className: 'oiib-confirmation-buttons' },
							createElement(
								'button',
								{
									type: 'button',
									className: 'oiib-btn oiib-btn-secondary',
									onClick: handleCancelSelection,
								},
								__('Cancel', 'jooosi-icon')
							),
							createElement(
								'button',
								{
									type: 'button',
									className: 'oiib-btn oiib-btn-primary',
									onClick: handleConfirmSelection,
								},
								__('Confirm', 'jooosi-icon')
							)
						)
					)
				) : null
			)
		) : null
	);
};

export default IconPickerModal;
