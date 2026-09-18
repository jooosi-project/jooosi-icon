import * as ReactDOM from 'react-dom';
import { StrictMode } from 'react';
import AdminApp from './components/AdminApp';
import './admin-app.scss';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('jooosi-icon-app');
	
	if (container) {
		const root = ReactDOM.createRoot(container);
		root.render(
			<StrictMode>
				<AdminApp />
			</StrictMode>
		);
	}
});
