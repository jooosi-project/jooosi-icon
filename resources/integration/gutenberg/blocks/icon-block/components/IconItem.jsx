import { memo, useState, useCallback } from '@wordpress/element';
import IconCheck from '~icons/tabler/check';

/**
 * IconItem Component
 * Displays a single icon in the icon picker grid
 * Memoized to prevent unnecessary re-renders
 */
const IconItem = memo(({ 
	icon, 
	isSelected, 
	isFocused,
	onSelect, 
	index 
}) => {
	const [isOverflowing, setIsOverflowing] = useState(false);
	const [scrollDistance, setScrollDistance] = useState('0px');
	const [scrollDuration, setScrollDuration] = useState('3s');

	const handleMouseEnter = useCallback((e) => {
		const nameEl = e.currentTarget.querySelector('.oiib-icon-name');
		if (nameEl && nameEl.scrollWidth > nameEl.clientWidth) {
			// Calculate distance to scroll
			const distance = nameEl.scrollWidth - nameEl.clientWidth;
			// Calculate duration: 25 pixels per second for constant speed
			const duration = (distance / 25);
			
			setScrollDistance(`-${distance}px`);
			setScrollDuration(`${duration}s`);
			setIsOverflowing(true);
		}
	}, []);

	const handleMouseLeave = useCallback(() => {
		setIsOverflowing(false);
	}, []);

	const handleClick = useCallback(() => {
		onSelect(icon.name);
	}, [icon.name, onSelect]);

	const handleKeyDown = useCallback((e) => {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			onSelect(icon.name);
		}
	}, [icon.name, onSelect]);

	return (
		<button
			className={`oiib-icon-item ${isSelected ? 'is-selected' : ''} ${isFocused ? 'is-focused' : ''}`}
			onClick={handleClick}
			onKeyDown={handleKeyDown}
			onMouseEnter={handleMouseEnter}
			onMouseLeave={handleMouseLeave}
			title={icon.name}
			data-index={index}
			tabIndex={isFocused ? 0 : -1}
			aria-label={`Select icon ${icon.name}`}
			aria-pressed={isSelected}
		>
			{isSelected && (
				<div className="oiib-icon-selected-badge">
					<IconCheck />
				</div>
			)}
			<div className="oiib-icon-preview">
				<jooosi-icon
					name={icon.name}
					width="32"
					height="32"
				/>
			</div>
			<div className="oiib-icon-label">
				<span 
					className={`oiib-icon-name ${isOverflowing ? 'is-overflowing' : ''}`}
					style={{
						'--scroll-distance': scrollDistance,
						'--scroll-duration': scrollDuration,
					}}
				>
					{icon.iconName}
				</span>
				<span className="oiib-icon-prefix">{icon.prefix}</span>
			</div>
		</button>
	);
});

IconItem.displayName = 'IconItem';

export default IconItem;
