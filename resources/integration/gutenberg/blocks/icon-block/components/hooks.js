import { useState, useEffect, useRef, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Custom hook for debouncing a value
 * @param {*} value - The value to debounce
 * @param {number} delay - Delay in milliseconds
 * @returns {*} Debounced value
 */
export const useDebounce = (value, delay = 300) => {
	const [debouncedValue, setDebouncedValue] = useState(value);

	useEffect(() => {
		const handler = setTimeout(() => {
			setDebouncedValue(value);
		}, delay);

		return () => {
			clearTimeout(handler);
		};
	}, [value, delay]);

	return debouncedValue;
};

/**
 * Custom hook for fetching icon collections
 * @param {boolean} isOpen - Whether the modal is open
 * @returns {Object} { collections, isLoading, error, refreshCollections }
 */
export const useIconCollections = (isOpen) => {
	const [collections, setCollections] = useState({});
	const [isLoading, setIsLoading] = useState(false);
	const [isRefreshing, setIsRefreshing] = useState(false);
	const [error, setError] = useState(null);
	const [refreshTrigger, setRefreshTrigger] = useState(0);
	const abortControllerRef = useRef(null);

	const refreshCollections = useCallback(() => {
		setRefreshTrigger(prev => prev + 1);
	}, []);

	useEffect(() => {
		if (!isOpen) return;

		const fetchCollections = async () => {
			// Cancel previous request if exists
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}

			const abortController = new AbortController();
			abortControllerRef.current = abortController;

			try {
				// Only show main loading on initial load, not on refresh
				if (refreshTrigger === 0) {
					setIsLoading(true);
				} else {
					setIsRefreshing(true);
				}
				setError(null);
				
				// Add cache-busting parameter when manually refreshing
				const cacheBuster = refreshTrigger > 0 ? `?_=${Date.now()}` : '';
				const response = await fetch(`/wp-json/jooosi-icon/v1/icon/collections${cacheBuster}`, {
					headers: { Accept: 'application/json' },
					signal: abortController.signal,
				});

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				const data = await response.json();
				setCollections(data.collections || {});
			} catch (err) {
				// Ignore abort errors
				if (err.name === 'AbortError') {
					return;
				}
				setError(__('Failed to load icon collections', 'jooosi-icon'));
				console.error('Error fetching collections:', err);
			} finally {
				if (abortControllerRef.current === abortController) {
					abortControllerRef.current = null;
					setIsLoading(false);
					setIsRefreshing(false);
				}
			}
		};

		fetchCollections();

		// Cleanup: abort any ongoing requests when modal closes
		return () => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
				abortControllerRef.current = null;
			}
		};
	}, [isOpen, refreshTrigger]);

	return { collections, isLoading, isRefreshing, error, refreshCollections };
};

/**
 * Custom hook for searching icons
 * @param {string} query - Search query
 * @param {boolean} isOpen - Whether the modal is open
 * @returns {Object} { icons, isLoading, error }
 */
export const useIconSearch = (query, isOpen) => {
	const [icons, setIcons] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState(null);
	const abortControllerRef = useRef(null);

	useEffect(() => {
		if (!isOpen || !query.trim()) {
			setIcons([]);
			return;
		}

		const searchIcons = async () => {
			// Cancel previous search request if exists
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}

			const abortController = new AbortController();
			abortControllerRef.current = abortController;

			try {
				setIsLoading(true);
				setError(null);

				const response = await fetch(
					`/wp-json/jooosi-icon/v1/icon/search?query=${encodeURIComponent(query)}`,
					{
						headers: { Accept: 'application/json' },
						signal: abortController.signal,
					}
				);

				if (!response.ok) {
					const errorData = await response.json();
					const errorMessage = errorData.message || `HTTP error! status: ${response.status}`;
					throw new Error(errorMessage);
				}

				const data = await response.json();

				// Format ALL results to include full icon name
				const formattedResults = (data.results || []).map(icon => ({
					name: `${icon.prefix}:${icon.name}`,
					prefix: icon.prefix,
					iconName: icon.name,
				}));

				setIcons(formattedResults);
			} catch (err) {
				// Ignore abort errors
				if (err.name === 'AbortError') {
					return;
				}
				const errorMessage = err.message || 'Unknown error';
				setError(__('Failed to search icons', 'jooosi-icon') + ': ' + errorMessage);
				console.error('Error searching icons:', err);
			} finally {
				if (abortControllerRef.current === abortController) {
					abortControllerRef.current = null;
					setIsLoading(false);
				}
			}
		};

		searchIcons();

		// Cleanup
		return () => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
				abortControllerRef.current = null;
			}
		};
	}, [query, isOpen]);

	return { icons, isLoading, error };
};

/**
 * Custom hook for generating default icons from collections
 * @param {Object} collections - Icon collections
 * @param {string} currentIcon - Currently selected icon
 * @returns {Array} Default icons array
 */
export const useDefaultIcons = (collections, currentIcon) => {
	return useMemo(() => {
		const defaultIcons = [];

		// Add current icon first if it exists
		if (currentIcon) {
			const [prefix, iconName] = currentIcon.split(':');
			if (prefix && iconName) {
				defaultIcons.push({
					name: currentIcon,
					prefix,
					iconName,
					isCurrent: true,
				});
			}
		}

		// Add samples from ALL collections
		Object.entries(collections).forEach(([prefix, collection]) => {
			if (collection.samples && Array.isArray(collection.samples)) {
				collection.samples.forEach(iconName => {
					const fullName = `${prefix}:${iconName}`;
					// Don't duplicate the current icon
					if (fullName !== currentIcon) {
						defaultIcons.push({
							name: fullName,
							prefix,
							iconName,
						});
					}
				});
			}
		});

		return defaultIcons;
	}, [collections, currentIcon]);
};

/**
 * Custom hook for filtering and paginating icons
 * @param {Array} allIcons - All available icons
 * @param {string} selectedCollection - Selected collection filter
 * @param {number} iconsPerPage - Number of icons per page
 * @returns {Object} { filteredIcons, paginatedIcons, totalPages, currentPage, setCurrentPage, collectionCounts }
 */
export const useIconFiltering = (allIcons, selectedCollection, iconsPerPage = 64) => {
	const [currentPage, setCurrentPage] = useState(1);

	// Memoize filtered icons by collection
	const filteredIcons = useMemo(() => {
		if (selectedCollection === 'all') {
			return allIcons;
		}
		return allIcons.filter(icon => icon.prefix === selectedCollection);
	}, [allIcons, selectedCollection]);

	// Calculate total pages
	const totalPages = useMemo(() => {
		return Math.ceil(filteredIcons.length / iconsPerPage);
	}, [filteredIcons.length, iconsPerPage]);

	// Reset to page 1 when collection changes or icons change
	useEffect(() => {
		setCurrentPage(1);
	}, [selectedCollection, allIcons]);

	// Memoize paginated icons
	const paginatedIcons = useMemo(() => {
		const startIndex = (currentPage - 1) * iconsPerPage;
		const endIndex = startIndex + iconsPerPage;
		return filteredIcons.slice(startIndex, endIndex);
	}, [filteredIcons, currentPage, iconsPerPage]);

	// Memoize collection counts
	const collectionCounts = useMemo(() => {
		const counts = {};
		allIcons.forEach(icon => {
			counts[icon.prefix] = (counts[icon.prefix] || 0) + 1;
		});
		return counts;
	}, [allIcons]);

	return {
		filteredIcons,
		paginatedIcons,
		totalPages,
		currentPage,
		setCurrentPage,
		collectionCounts,
	};
};

/**
 * Custom hook for keyboard navigation in icon grid
 * @param {Array} icons - Array of icons to navigate
 * @param {Function} onSelectIcon - Callback when icon is selected
 * @param {Function} onClose - Callback to close modal
 * @param {boolean} isOpen - Whether the modal is open
 * @returns {Object} { selectedIndex, setSelectedIndex, handleKeyDown }
 */
export const useKeyboardNavigation = (icons, onSelectIcon, onClose, isOpen) => {
	const [selectedIndex, setSelectedIndex] = useState(-1);
	const gridRef = useRef(null);

	// Reset selected index when icons change or modal opens
	useEffect(() => {
		if (isOpen) {
			setSelectedIndex(-1);
		}
	}, [icons, isOpen]);

	// Calculate actual number of columns in the grid
	const getColumnCount = useCallback(() => {
		if (!gridRef.current) return 1;
		
		// Get the first two items in the grid
		const items = gridRef.current.querySelectorAll('.oiib-icon-item');
		if (items.length < 2) return 1;
		
		// Calculate column count by checking how many items fit in one row
		const firstItemTop = items[0].getBoundingClientRect().top;
		let cols = 1;
		
		for (let i = 1; i < items.length; i++) {
			const itemTop = items[i].getBoundingClientRect().top;
			// If item is on the same row as the first item
			if (Math.abs(itemTop - firstItemTop) < 5) {
				cols++;
			} else {
				// We've reached the second row, stop counting
				break;
			}
		}
		
		return cols;
	}, []);

	const handleKeyDown = useCallback((event) => {
		if (!icons.length) return;
		
		switch (event.key) {
			case 'ArrowRight':
				event.preventDefault();
				setSelectedIndex(prev => {
					// If nothing selected, start at index 0
					if (prev === -1) return 0;
					const next = prev + 1;
					return next >= icons.length ? prev : next;
				});
				break;

			case 'ArrowLeft':
				event.preventDefault();
				setSelectedIndex(prev => {
					// If nothing selected, start at index 0
					if (prev === -1) return 0;
					const next = prev - 1;
					return next < 0 ? 0 : next;
				});
				break;

			case 'ArrowDown':
				event.preventDefault();
				setSelectedIndex(prev => {
					// If nothing selected, start at index 0
					if (prev === -1) return 0;
					const cols = getColumnCount();
					const next = prev + cols;
					return next >= icons.length ? prev : next;
				});
				break;

			case 'ArrowUp':
				event.preventDefault();
				setSelectedIndex(prev => {
					// If nothing selected, start at index 0
					if (prev === -1) return 0;
					const cols = getColumnCount();
					const next = prev - cols;
					return next < 0 ? prev : next;
				});
				break;

			case 'Enter':
			case ' ':
				event.preventDefault();
				if (selectedIndex >= 0 && selectedIndex < icons.length) {
					onSelectIcon(icons[selectedIndex].name);
				}
				break;

			case 'Escape':
				event.preventDefault();
				onClose();
				break;

			case 'Home':
				event.preventDefault();
				setSelectedIndex(0);
				break;

			case 'End':
				event.preventDefault();
				setSelectedIndex(icons.length - 1);
				break;

			default:
				break;
		}
	}, [icons, selectedIndex, onSelectIcon, onClose, getColumnCount]);

	// Scroll selected item into view
	useEffect(() => {
		if (selectedIndex >= 0 && gridRef.current) {
			const selectedElement = gridRef.current.querySelector(`[data-index="${selectedIndex}"]`);
			if (selectedElement) {
				selectedElement.scrollIntoView({
					behavior: 'smooth',
					block: 'nearest',
				});
			}
		}
	}, [selectedIndex]);

	return { selectedIndex, setSelectedIndex, handleKeyDown, gridRef };
};
