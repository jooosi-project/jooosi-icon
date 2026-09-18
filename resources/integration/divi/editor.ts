import metadata from './module.json';
import '../gutenberg/blocks/icon-block/editor.css';
import '../elementor/editor.scss';
import './editor.css';
import { createJooosiIconPickerField } from './icon-picker-field.js';
import { openIconPicker, renderModal } from './editor-app.jsx';

const elementApi = window?.vendor?.wp?.element || window?.wp?.element;
const addAction = window?.vendor?.wp?.hooks?.addAction || window?.wp?.hooks?.addAction;
const modulePackage = window?.divi?.module;
const registerModule = window?.divi?.moduleLibrary?.registerModule;

if (elementApi && addAction && modulePackage && registerModule) {
	const createElement = elementApi.createElement;
	const Fragment = elementApi.Fragment;
	const JooosiIconPickerField = createJooosiIconPickerField(elementApi, {
		openIconPicker,
		renderModal,
	});
	const ModuleContainer = modulePackage.ModuleContainer;
	const StyleContainer = modulePackage.StyleContainer;
	const elementClassnames = modulePackage.elementClassnames;

	const resolveFieldValue = (value) => {
		if (typeof value === 'string' || typeof value === 'number') {
			return String(value);
		}

		if (!value || typeof value !== 'object') {
			return '';
		}

		if (value.desktop && Object.prototype.hasOwnProperty.call(value.desktop, 'value')) {
			return resolveFieldValue(value.desktop.value);
		}

		if (Object.prototype.hasOwnProperty.call(value, 'value')) {
			return resolveFieldValue(value.value);
		}

		return '';
	};

	const getFieldValue = (attrs, attributeName) => resolveFieldValue(attrs?.[attributeName]?.innerContent).trim();

	const normalizeDimensions = (width, height) => {
		const nextWidth = width || height || '48px';
		const nextHeight = height || width || '48px';

		return {
			width: nextWidth,
			height: nextHeight,
		};
	};

	const renderIconPreview = (iconName, width, height, color) => {
		const normalizedIconName = (iconName || '').trim();

		if (!normalizedIconName) {
			return createElement(
				'div',
				{
					style: {
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						minHeight: '96px',
						padding: '16px',
						border: '1px dashed rgba(15, 23, 42, 0.18)',
						borderRadius: '12px',
						background: 'rgba(248, 250, 252, 0.8)',
						fontSize: '13px',
						lineHeight: '1.6',
						color: '#475569',
						textAlign: 'center',
					},
				},
				'Enter an icon name such as mdi:home.'
			);
		}

		const dimensions = normalizeDimensions(width, height);

		return createElement(
			'div',
			{
				style: {
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center',
					padding: '16px',
					minHeight: '96px',
					lineHeight: 0,
				},
			},
			createElement('jooosi-icon', {
				name: normalizedIconName,
				width: dimensions.width,
				height: dimensions.height,
				color: (color || '').trim() || 'currentColor',
			})
		);
	};

	const ModuleStyles = ({ elements, mode, noStyleTag, settings, state }) => createElement(
		StyleContainer,
		{ mode, noStyleTag, state },
		elements.style({
			attrName: 'module',
			styleProps: {
				disabledOn: {
					disabledModuleVisibility: settings?.disabledModuleVisibility,
				},
			},
		})
	);

	const ModuleScriptData = ({ elements }) => createElement(
		Fragment,
		null,
		elements.scriptData({ attrName: 'module' })
	);

	const moduleClassnames = ({ attrs, classnamesInstance }) => {
		classnamesInstance.add(
			elementClassnames({
				attrs: attrs?.module?.decoration || {},
			})
		);
	};

	const diviIconModule = {
		metadata,
		renderers: {
			edit: ({ attrs, elements, id, name }) => {
				const iconName = getFieldValue(attrs, 'iconName') || 'mdi:home';
				const width = getFieldValue(attrs, 'iconWidth') || '48px';
				const height = getFieldValue(attrs, 'iconHeight') || width;
				const color = getFieldValue(attrs, 'iconColor') || 'currentColor';

				return createElement(
					ModuleContainer,
					{
						attrs,
						elements,
						id,
						moduleClassName: 'jooosi_icon_divi_module',
						name,
						scriptDataComponent: ModuleScriptData,
						stylesComponent: ModuleStyles,
						classnamesFunction: moduleClassnames,
					},
					elements.styleComponents({ attrName: 'module' }),
					createElement(
						'div',
						{ className: 'et_pb_module_inner' },
						renderIconPreview(iconName, width, height, color)
					)
				);
			},
		},
		placeholderContent: {
			iconName: {
				innerContent: {
					desktop: {
						value: 'mdi:home',
					},
				},
			},
			iconWidth: {
				innerContent: {
					desktop: {
						value: '48px',
					},
				},
			},
			iconHeight: {
				innerContent: {
					desktop: {
						value: '48px',
					},
				},
			},
			iconColor: {
				innerContent: {
					desktop: {
						value: 'currentColor',
					},
				},
			},
		},
	};

	const registerJooosiIconField = () => {
		if (window.jooosiIconDiviFieldRegistered) {
			return;
		}

		const registerFieldComponent = window?.divi?.fieldLibrary?.registerFieldComponent;
		if (typeof registerFieldComponent !== 'function') {
			return;
		}

		try {
			registerFieldComponent({
				name: 'jooosi-icon/icon-picker',
				component: JooosiIconPickerField,
			});
			window.jooosiIconDiviFieldRegistered = true;
		} catch (error) {
			console.warn('[Jooosi Icon] Divi field registration is waiting for the field library.', error);
		}
	};

	const registerJooosiIconModule = () => {
		if (window.jooosiIconDiviModuleRegistered) {
			return;
		}

		const moduleDefinition = {
			renderers: diviIconModule.renderers,
			placeholderContent: diviIconModule.placeholderContent,
		};

		try {
			registerModule(diviIconModule.metadata, moduleDefinition);
			window.jooosiIconDiviModuleRegistered = true;
		} catch (error) {
			console.warn('[Jooosi Icon] Divi module registration is waiting for the module library store.', error);
		}
	};

	addAction(
		'divi.moduleLibrary.registerModuleLibraryStore.after',
		'jooosiIcon.divi.module',
		() => {
			registerJooosiIconField();
			registerJooosiIconModule();
		}
	);

	window.setTimeout(() => {
		renderModal();
		registerJooosiIconField();
		registerJooosiIconModule();
	}, 0);
} else {
	console.warn('[Jooosi Icon] Divi 5 builder dependencies are not available.');
}
