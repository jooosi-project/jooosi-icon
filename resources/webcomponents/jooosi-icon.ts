import './jooosi-icon.scss';
import { connectElement, disconnectElement } from './JooosiIconObserver';

class JooosiIcon extends HTMLElement {
	static observedAttributes = ['data-prerendered'];

	connectedCallback(): void {
		connectElement(this);
	}

	disconnectedCallback(): void {
		disconnectElement(this);
	}

	attributeChangedCallback(): void {
		// Begin client rendering if server-rendered content is released by an editor.
		connectElement(this);
	}
}

class LegacyJooosiIcon extends JooosiIcon {}

if ('customElements' in window) {
	if (!customElements.get('jooosi-icon')) {
		customElements.define('jooosi-icon', JooosiIcon);
	}

	// Backward compatibility for content saved before the Jooosi Icon rebrand.
	if (!customElements.get('omni-icon')) {
		customElements.define('omni-icon', LegacyJooosiIcon);
	}
}

export default JooosiIcon;
