import { useState, useEffect, useRef, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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

export const useIconCollections = (isOpen) => {
	const [collections, setCollections] = useState({});
	const [isLoading, setIsLoading] = useState(false);
	const [isRefreshing, setIsRefreshing] = useState(false);
	const [error, setError] = useState(null);
	const [refreshTrigger, setRefreshTrigger] = useState(0);
	const abortControllerRef = useRef(null);

	const refreshCollections = useCallback(() => {
		setRefreshTrigger((previous) => previous + 1);
	}, []);

	useEffect(() => {
		if (!isOpen) {
			return undefined;
		}

		const fetchCollections = async () => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}

			const abortController = new AbortController();
			abortControllerRef.current = abortController;

			try {
				if (refreshTrigger === 0) {
					setIsLoading(true);
				} else {
					setIsRefreshing(true);
				}

				setError(null);

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
			} catch (error) {
				if (error.name === 'AbortError') {
					return;
				}

				setError(__('Failed to load icon collections', 'jooosi-icon'));
				console.error('Error fetching collections:', error);
			} finally {
				if (abortControllerRef.current === abortController) {
					abortControllerRef.current = null;
					setIsLoading(false);
					setIsRefreshing(false);
				}
			}
		};

		fetchCollections();

		return () => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
				abortControllerRef.current = null;
			}
		};
	}, [isOpen, refreshTrigger]);

	return { collections, isLoading, isRefreshing, error, refreshCollections };
};

export const useIconSearch = (query, isOpen) => {
	const [icons, setIcons] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState(null);
	const abortControllerRef = useRef(null);

	useEffect(() => {
		if (!isOpen || !query.trim()) {
			setIcons([]);
			return undefined;
		}

		const searchIcons = async () => {
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
					throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
				}

				const data = await response.json();
				const formattedResults = (data.results || []).map((icon) => ({
					name: `${icon.prefix}:${icon.name}`,
					prefix: icon.prefix,
					iconName: icon.name,
				}));

				setIcons(formattedResults);
			} catch (error) {
				if (error.name === 'AbortError') {
					return;
				}

				setError(`${__('Failed to search icons', 'jooosi-icon')}: ${error.message || 'Unknown error'}`);
				console.error('Error searching icons:', error);
			} finally {
				if (abortControllerRef.current === abortController) {
					abortControllerRef.current = null;
					setIsLoading(false);
				}
			}
		};

		searchIcons();

		return () => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
				abortControllerRef.current = null;
			}
		};
	}, [query, isOpen]);

	return { icons, isLoading, error };
};

export const useDefaultIcons = (collections, currentIcon) => useMemo(() => {
	const defaultIcons = [];

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

	Object.entries(collections).forEach(([prefix, collection]) => {
		if (Array.isArray(collection.samples)) {
			collection.samples.forEach((iconName) => {
				const fullName = `${prefix}:${iconName}`;
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

export const useIconFiltering = (allIcons, selectedCollection, iconsPerPage = 64) => {
	const [currentPage, setCurrentPage] = useState(1);

	const filteredIcons = useMemo(() => {
		if (selectedCollection === 'all') {
			return allIcons;
		}

		return allIcons.filter((icon) => icon.prefix === selectedCollection);
	}, [allIcons, selectedCollection]);

	const totalPages = useMemo(() => Math.ceil(filteredIcons.length / iconsPerPage), [filteredIcons.length, iconsPerPage]);

	useEffect(() => {
		setCurrentPage(1);
	}, [selectedCollection, allIcons]);

	const paginatedIcons = useMemo(() => {
		const startIndex = (currentPage - 1) * iconsPerPage;
		return filteredIcons.slice(startIndex, startIndex + iconsPerPage);
	}, [currentPage, filteredIcons, iconsPerPage]);

	const collectionCounts = useMemo(() => {
		const counts = {};
		allIcons.forEach((icon) => {
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
