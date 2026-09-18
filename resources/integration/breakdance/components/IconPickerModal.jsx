import { Modal, Spinner } from '@wordpress/components';
import { useState, useCallback, useMemo, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import IconSearch from '~icons/tabler/search';
import IconX from '~icons/tabler/x';
import IconChevronLeft from '~icons/tabler/chevron-left';
import IconChevronRight from '~icons/tabler/chevron-right';
import IconRefresh from '~icons/tabler/refresh';

// Import custom hooks from Gutenberg
import {
	useDebounce,
	useIconCollections,
	useIconSearch,
	useDefaultIcons,
	useIconFiltering,
	useKeyboardNavigation,
} from '../../gutenberg/blocks/icon-block/components/hooks';

// Import IconItem component from Gutenberg
import IconItem from '../../gutenberg/blocks/icon-block/components/IconItem';

const ICONS_PER_PAGE = 64;

/**
 * Icon Picker Modal for Breakdance Builder
 * Reuses the Gutenberg IconPickerModal component
 */
const IconPickerModal = ({ isOpen, onClose, onSelectIcon, currentIcon }) => {
	// Search state
	const [searchQuery, setSearchQuery] = useState('');
	const debouncedSearchQuery = useDebounce(searchQuery, 300);
	
	// Collection filter state
	const [selectedCollection, setSelectedCollection] = useState('all');
	
	// Temporary selection state (before confirmation)
	const [tempSelectedIcon, setTempSelectedIcon] = useState(currentIcon || null);
	
	// Ref for search input to manage focus
	const searchInputRef = useRef(null);
	
	// Fetch collections
	const { collections, isLoading: isLoadingCollections, isRefreshing, error: collectionsError, refreshCollections } = useIconCollections(isOpen);
	
	// Search icons or use default
	const { icons: searchResults, isLoading: isSearching, error: searchError } = useIconSearch(debouncedSearchQuery, isOpen);
	
	// Generate default icons from collections
	const defaultIcons = useDefaultIcons(collections, currentIcon);
	
	// Determine which icons to display (search results or default)
	const allIcons = useMemo(() => {
		return debouncedSearchQuery.trim() ? searchResults : defaultIcons;
	}, [debouncedSearchQuery, searchResults, defaultIcons]);
	
	// Filter and paginate icons
	const {
		paginatedIcons,
		totalPages,
		currentPage,
		setCurrentPage,
		collectionCounts,
	} = useIconFiltering(allIcons, selectedCollection, ICONS_PER_PAGE);
	
	// Determine loading state - only show loading overlay on initial load, not on refresh
	const isLoading = isLoadingCollections || isSearching;
	const error = collectionsError || searchError;
	
	// Memoized callbacks
	const handleSelectIcon = useCallback((iconName) => {
		// Toggle selection: if clicking the same icon, deselect it
		setTempSelectedIcon(prevIcon => prevIcon === iconName ? null : iconName);
	}, []);
	
	const handleConfirmSelection = useCallback(() => {
		// Allow confirming with null to clear the icon
		onSelectIcon(tempSelectedIcon || '');
		onClose();
	}, [tempSelectedIcon, onSelectIcon, onClose]);
	
	const handleCancelSelection = useCallback(() => {
		onClose();
	}, [onClose]);
	
	// Keyboard navigation
	const { selectedIndex, handleKeyDown: baseHandleKeyDown, gridRef } = useKeyboardNavigation(
		paginatedIcons,
		handleSelectIcon,
		handleCancelSelection,
		isOpen
	);
	
	// Wrap keyboard handler to add Ctrl+Enter for confirm
	const handleKeyDown = useCallback((event) => {
		// Ctrl/Cmd + Enter to confirm selection
		if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
			event.preventDefault();
			handleConfirmSelection();
			return;
		}
		
		// Pass to base handler
		baseHandleKeyDown(event);
	}, [baseHandleKeyDown, handleConfirmSelection]);
	
	const handleClearSearch = useCallback(() => {
		setSearchQuery('');
	}, []);
	
	const handleCollectionFilter = useCallback((collectionPrefix) => {
		setSelectedCollection(collectionPrefix);
	}, []);
	
	const handlePageChange = useCallback((newPage) => {
		if (newPage < 1 || newPage > totalPages) return;
		setCurrentPage(newPage);
	}, [totalPages, setCurrentPage]);
	
	const handlePrevPage = useCallback(() => {
		if (currentPage > 1) {
			handlePageChange(currentPage - 1);
		}
	}, [currentPage, handlePageChange]);
	
	const handleNextPage = useCallback(() => {
		if (currentPage < totalPages) {
			handlePageChange(currentPage + 1);
		}
	}, [currentPage, totalPages, handlePageChange]);
	
	// Calculate total count for current filter
	const totalCount = useMemo(() => {
		if (selectedCollection === 'all') {
			return allIcons.length;
		}
		return allIcons.filter(icon => icon.prefix === selectedCollection).length;
	}, [allIcons, selectedCollection]);
	
	// Reset search when modal closes/opens
	useEffect(() => {
		if (isOpen) {
			setTempSelectedIcon(currentIcon || null);
			// Add body class to prevent scrolling
			document.body.classList.add('oibd-modal-open');
			// Focus search input after modal opens
			setTimeout(() => {
				if (searchInputRef.current) {
					searchInputRef.current.focus();
				}
			}, 100);
		} else {
			// Small delay before resetting to allow close animation
			const timeout = setTimeout(() => {
				setSearchQuery('');
				setSelectedCollection('all');
				setTempSelectedIcon(null);
			}, 200);
			// Remove body class to restore scrolling
			document.body.classList.remove('oibd-modal-open');
			return () => clearTimeout(timeout);
		}
	}, [isOpen, currentIcon]);
	
	// Handle Escape key to close modal
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
	}, [isOpen, onClose]);
	
	// Info message
	const infoMessage = useMemo(() => {
		// Show searching message when loading
		if (isLoading) {
			if (searchQuery) {
				const collectionName = selectedCollection === 'all' 
					? '' 
					: ` in ${collections[selectedCollection]?.name || selectedCollection}`;
				return __(`Searching for "${searchQuery}"${collectionName}...`, 'jooosi-icon');
			}
			return __('Loading icons...', 'jooosi-icon');
		}
		
		if (debouncedSearchQuery) {
			const collectionName = selectedCollection === 'all' 
				? '' 
				: ` in ${collections[selectedCollection]?.name || selectedCollection}`;
			
			return totalCount > 0
				? __(`Found ${totalCount} icons for "${debouncedSearchQuery}"${collectionName}`, 'jooosi-icon')
				: __(`No icons found for "${debouncedSearchQuery}"${collectionName}`, 'jooosi-icon');
		}
		
		return selectedCollection === 'all'
			? __('Showing sample icons from all collections', 'jooosi-icon')
			: __(`Showing sample icons from ${collections[selectedCollection]?.name || selectedCollection}`, 'jooosi-icon');
	}, [isLoading, searchQuery, debouncedSearchQuery, selectedCollection, totalCount, collections]);

	return (
		<>
			{isOpen && (
				<Modal
					title={__('Jooosi Icon — Icon Picker', 'jooosi-icon')}
					onRequestClose={onClose}
					className="oiib-icon-picker-modal oibd-icon-picker-modal"
					size="large"
					onKeyDown={handleKeyDown}
					aria-label={__('Icon Picker', 'jooosi-icon')}
				>
					<div className="oiib-icon-picker-content">
						{/* Search Bar */}
						<div className="oiib-icon-picker-search">
							<div className="oiib-search-wrapper">
								<IconSearch className="oiib-search-icon" aria-hidden="true" />
								<input
									ref={searchInputRef}
									type="text"
									className="oiib-search-input"
									value={searchQuery}
									onChange={(e) => setSearchQuery(e.target.value)}
									placeholder={__('Search icons... (e.g., home, mdi:heart, lucide:star)', 'jooosi-icon')}
									aria-label={__('Search icons', 'jooosi-icon')}
								/>
								{searchQuery && (
									<button
										className="oiib-search-clear"
										onClick={handleClearSearch}
										aria-label={__('Clear search', 'jooosi-icon')}
									>
										<IconX />
									</button>
								)}
							</div>
							<button
								className="oiib-search-refresh"
								onClick={refreshCollections}
								disabled={isRefreshing}
								aria-label={__('Refresh icon collections', 'jooosi-icon')}
								title={__('Refresh icon collections', 'jooosi-icon')}
							>
								<IconRefresh className={isRefreshing ? 'is-spinning' : ''} />
							</button>
						</div>

						{/* Collection Filter */}
						{Object.keys(collections).length > 0 && (
							<div className="oiib-collection-filter" role="tablist" aria-label={__('Filter by collection', 'jooosi-icon')}>
								<div className="oiib-collection-filter-wrapper">
									<button
										className={`oiib-collection-chip ${selectedCollection === 'all' ? 'is-active' : ''}`}
										onClick={() => handleCollectionFilter('all')}
										role="tab"
										aria-selected={selectedCollection === 'all'}
										aria-label={__('All collections', 'jooosi-icon')}
									>
										{__('All', 'jooosi-icon')}
										<span className="oiib-collection-count" aria-label={`${allIcons.length} icons`}>
											{allIcons.length}
										</span>
									</button>
									{Object.entries(collections).map(([prefix, collection]) => {
										const count = collectionCounts[prefix] || 0;
										// Hide collections with 0 results when searching
										if (debouncedSearchQuery && count === 0) {
											return null;
										}
										return (
											<button
												key={prefix}
												className={`oiib-collection-chip ${selectedCollection === prefix ? 'is-active' : ''}`}
												onClick={() => handleCollectionFilter(prefix)}
												title={collection.name}
												data-count={count}
												role="tab"
												aria-selected={selectedCollection === prefix}
												aria-label={`${collection.name || prefix}, ${count} icons`}
											>
												{collection.name || prefix}
												<span className="oiib-collection-count">
													{count}
												</span>
											</button>
										);
									})}
								</div>
							</div>
						)}

					{/* Results Info with Pagination */}
					<div className="oiib-icon-picker-info">
						<div className="oiib-info-text">
							<p>{infoMessage}</p>
						</div>
						
						{/* Pagination Controls */}
						{!isLoading && debouncedSearchQuery && totalPages > 1 && (
							<div className="oiib-pagination" role="navigation" aria-label={__('Icon pagination', 'jooosi-icon')}>
								<button
									className="oiib-pagination-btn"
									onClick={handlePrevPage}
									disabled={currentPage === 1}
									aria-label={__('Previous page', 'jooosi-icon')}
								>
									<IconChevronLeft />
								</button>
								<span className="oiib-pagination-info" aria-live="polite">
									{__(`Page ${currentPage} of ${totalPages}`, 'jooosi-icon')}
								</span>
								<button
									className="oiib-pagination-btn"
									onClick={handleNextPage}
									disabled={currentPage === totalPages}
									aria-label={__('Next page', 'jooosi-icon')}
								>
									<IconChevronRight />
								</button>
							</div>
						)}
					</div>

					{/* Content Wrapper - prevents blinking on state changes */}
					<div className="oiib-icon-picker-content-wrapper">
						{/* Loading State */}
						{isLoading && (
							<div className="oiib-icon-picker-loading" role="status" aria-live="polite">
								<Spinner />
								<p>{__('Loading icons...', 'jooosi-icon')}</p>
							</div>
						)}

						{/* Error State */}
						{error && (
							<div className="oiib-icon-picker-error" role="alert">
								<p>{error}</p>
							</div>
						)}

						{/* Icon Grid */}
						{!isLoading && !error && paginatedIcons.length > 0 && (
							<div 
								className="oiib-icon-picker-grid" 
								ref={gridRef}
								role="grid"
								aria-label={__('Icon grid', 'jooosi-icon')}
							>
								{paginatedIcons.map((icon, index) => (
									<IconItem
										key={icon.name}
										icon={icon}
										isSelected={icon.name === tempSelectedIcon}
										isFocused={index === selectedIndex}
										onSelect={handleSelectIcon}
										index={index}
									/>
								))}
							</div>
						)}

						{/* Empty State */}
						{!isLoading && !error && paginatedIcons.length === 0 && debouncedSearchQuery && (
							<div className="oiib-icon-picker-empty" role="status">
								<IconSearch style={{ width: '48px', height: '48px' }} aria-hidden="true" />
								<h3>{__('No icons found', 'jooosi-icon')}</h3>
								<p>{__('Try a different search term or icon prefix', 'jooosi-icon')}</p>
							</div>
						)}
					</div>

					{/* Confirmation Footer */}
					{tempSelectedIcon !== currentIcon && (tempSelectedIcon || currentIcon) && (
						<div className="oiib-icon-picker-footer">
							<div className="oiib-selected-icon-preview">
								{tempSelectedIcon ? (
									<div className="oiib-selected-icon-display">
										<jooosi-icon
											name={tempSelectedIcon}
											width="32"
											height="32"
										/>
										<div className="oiib-selected-icon-info">
											<span className="oiib-selected-icon-label">{__('Selected:', 'jooosi-icon')}</span>
											<span className="oiib-selected-icon-name">{tempSelectedIcon}</span>
										</div>
									</div>
								) : (
									<div className="oiib-selected-icon-display">
										<div className="oiib-selected-icon-info">
											<span className="oiib-selected-icon-label">{__('Icon will be removed', 'jooosi-icon')}</span>
										</div>
									</div>
								)}
								<div className="oiib-confirmation-buttons">
									<button
										className="oiib-btn oiib-btn-secondary"
										onClick={handleCancelSelection}
										aria-label={__('Cancel', 'jooosi-icon')}
									>
										{__('Cancel', 'jooosi-icon')}
									</button>
									<button
										className="oiib-btn oiib-btn-primary"
										onClick={handleConfirmSelection}
										aria-label={__('Confirm selection', 'jooosi-icon')}
									>
										{__('Confirm', 'jooosi-icon')}
									</button>
								</div>
							</div>
						</div>
					)}
				</div>
				</Modal>
			)}
		</>
	);
};

export default IconPickerModal;
