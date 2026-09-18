import IconSearch from '~icons/tabler/search';
export function createJooosiIconPickerField(elementApi, pickerApi = {}) {
	const { createElement, useCallback, useEffect, useState } = elementApi;
	const { openIconPicker, renderModal } = pickerApi;

	const normalizeString = (value) => (typeof value === 'string' ? value.trim() : '');
	const hasOwnValue = (value) => value !== undefined && value !== null;

	const JooosiIconPickerField = ({ value, defaultValue, onChange, placeholder, fieldRef }) => {
		const hasExplicitValue = hasOwnValue(value);
		const currentValue = hasExplicitValue ? normalizeString(value) : normalizeString(defaultValue);
		const [inputValue, setInputValue] = useState(currentValue);

		useEffect(() => {
			setInputValue(currentValue);
		}, [currentValue]);

		const applyValue = useCallback((nextValue) => {
			if (typeof onChange === 'function') {
				onChange({
					inputValue: nextValue,
				});
			}
		}, [onChange]);

		const handleFieldChange = useCallback((event) => {
			const nextValue = event.target.value;
			setInputValue(nextValue);
			applyValue(nextValue);
		}, [applyValue]);

		const handleSearchClick = useCallback(() => {
			if (typeof renderModal === 'function') {
				renderModal();
			}

			if (typeof openIconPicker === 'function') {
				openIconPicker(inputValue || currentValue, (iconName) => {
					const nextValue = iconName || '';
					setInputValue(nextValue);
					applyValue(nextValue);
				});
			}
		}, [applyValue, currentValue, inputValue, openIconPicker, renderModal]);

		return createElement(
			'div',
			{ className: 'jooosi-icon-divi-field' },
			createElement(
				'div',
				{ className: 'jooosi-icon-divi-field__preview' },
				currentValue
					? createElement('jooosi-icon', {
						name: currentValue,
						width: '24',
						height: '24',
					})
					: createElement('span', { className: 'jooosi-icon-divi-field__placeholder' }, '?')
			),
			createElement(
				'div',
				{ className: 'jooosi-icon-divi-field__input-wrap' },
				createElement('input', {
					type: 'text',
					className: 'jooosi-icon-divi-field__input',
					value: inputValue,
					onChange: handleFieldChange,
					placeholder: normalizeString(placeholder) || 'mdi:home',
					'aria-label': 'Icon name',
					ref: fieldRef,
				}),
				createElement(
					'button',
					{
						type: 'button',
						className: 'jooosi-icon-divi-field__search-button',
						onClick: handleSearchClick,
						'aria-label': 'Search icons',
						title: 'Search icons',
					},
					createElement(IconSearch, null)
				)
			)
		);
	};

	JooosiIconPickerField.componentName = 'jooosi-icon/icon-picker';

	return JooosiIconPickerField;
}
