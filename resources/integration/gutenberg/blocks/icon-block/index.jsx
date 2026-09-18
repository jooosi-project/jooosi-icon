import './editor.css';

import { registerBlockType } from '@wordpress/blocks';
import JooosiIconSvg from '~/jooosi-icon.svg?react';
import Edit from './components/Edit';
import Save from './components/Save';
import metadata from './block.json';

// Jooosi Icon component
const icon = () => (
	<JooosiIconSvg width={24} height={24} aria-hidden="true" focusable="false" />
);

// Remove editorScript from metadata before registering
// (it's needed in block.json for WordPress Block Directory validation,
// but not needed here since we're registering via JS)
const { editorScript, name, ...blockSettings } = metadata;

registerBlockType(name, {
	...blockSettings,
	icon,
	edit: Edit,
	save: Save,
});
