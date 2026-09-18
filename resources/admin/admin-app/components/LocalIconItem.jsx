import { memo, useState, useCallback } from 'react';
import IconCheck from '~icons/tabler/check';

/**
 * LocalIconItem Component
 * Displays a single local icon in the manager grid
 * Similar to IconItem but with drag and drop support
 */
const LocalIconItem = memo(({ 
	icon, 
	isSelected,
	onSelect,
	onDragStart,
	onDragEnd,
	index 
}) => {
	const [isOverflowing, setIsOverflowing] = useState(false);
	const [scrollDistance, setScrollDistance] = useState('0px');
	const [scrollDuration, setScrollDuration] = useState('3s');

	const handleMouseEnter = useCallback((e) => {
		const nameEl = e.currentTarget.querySelector('.jooosi-icon-item-name');
		if (nameEl && nameEl.scrollWidth > nameEl.clientWidth) {
			const distance = nameEl.scrollWidth - nameEl.clientWidth;
			const duration = (distance / 25);
			
			setScrollDistance(`-${distance}px`);
			setScrollDuration(`${duration}s`);
			setIsOverflowing(true);
		}
	}, []);

	const handleMouseLeave = useCallback(() => {
		setIsOverflowing(false);
	}, []);

	const handleClick = useCallback((e) => {
		e.stopPropagation(); // Prevent grid mousedown
		onSelect(icon.name, index, e);
	}, [icon.name, index, onSelect]);

	const handleKeyDown = useCallback((e) => {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			onSelect(icon.name, index, e);
		}
	}, [icon.name, index, onSelect]);

	const handleDragStart = useCallback((e) => {
		// Find the full icon data to pass
		const iconData = {
			icon_name: icon.name,
			name: icon.iconName,
			icon_set: icon.prefix,
		};
		onDragStart(e, iconData);
	}, [icon, onDragStart]);

	return (
		<button
			className={`jooosi-icon-item ${isSelected ? 'is-selected' : ''}`}
			onClick={handleClick}
			onKeyDown={handleKeyDown}
			onMouseEnter={handleMouseEnter}
			onMouseLeave={handleMouseLeave}
			onDragStart={handleDragStart}
			onDragEnd={onDragEnd}
			draggable={true}
			title={icon.name}
			data-index={index}
			tabIndex={0}
			aria-label={`Select icon ${icon.name}`}
			aria-pressed={isSelected}
		>
			{isSelected && (
				<div className="jooosi-icon-selected-badge">
					<IconCheck />
				</div>
			)}
			<div className="jooosi-icon-preview">
				<jooosi-icon
					name={icon.name}
					width="32"
					height="32"
				/>
			</div>
			<div className="jooosi-icon-label">
				<span 
					className={`jooosi-icon-item-name ${isOverflowing ? 'is-overflowing' : ''}`}
					style={{
						'--scroll-distance': scrollDistance,
						'--scroll-duration': scrollDuration,
					}}
				>
					{icon.iconName}
				</span>
				<span className="jooosi-icon-prefix">{icon.prefix}</span>
			</div>
		</button>
	);
});

LocalIconItem.displayName = 'LocalIconItem';

export default LocalIconItem;
