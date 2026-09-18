import './jooosi-icon.scss';
import { getRenderer, clearSeenElement } from './JooosiIconObserver';

class JooosiIcon extends HTMLElement {
	disconnectedCallback(): void {
		getRenderer()?.detachRenderer(this);
		clearSeenElement(this);
	}

	// restartAnimation(): void {
	// 	getRenderer()?.restartAnimation(this);
	// }
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
