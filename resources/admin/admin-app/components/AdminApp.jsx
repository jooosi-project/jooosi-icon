import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import IconManager from './IconManager';
import HelpTab from './HelpTab';
import AboutTab from './AboutTab';

const AdminApp = () => {
	const [refreshTrigger] = useState(0);
	const [activeTab, setActiveTab] = useState('icons');

	const tabs = [
		{ id: 'icons', label: __('Icons', 'jooosi-icon') },
		// { id: 'help', label: __('Help', 'jooosi-icon') },
		{ id: 'about', label: __('About', 'jooosi-icon') },
	];

	return (
		<div className="jooosi-icon-admin-wrapper">
			<div className="jooosi-icon-admin-header">
				<h1>{__('Jooosi Icon', 'jooosi-icon')}</h1>
			</div>

			<nav className="jooosi-icon-admin-tabs">
				{tabs.map((tab) => (
					<button
						key={tab.id}
						className={`jooosi-icon-tab ${activeTab === tab.id ? 'is-active' : ''}`}
						onClick={() => setActiveTab(tab.id)}
					>
						{tab.label}
					</button>
				))}
			</nav>

			<div className="jooosi-icon-admin-content">
				{activeTab === 'icons' && <IconManager refreshTrigger={refreshTrigger} />}
				{activeTab === 'help' && <HelpTab />}
				{activeTab === 'about' && <AboutTab />}
			</div>
		</div>
	);
};

export default AdminApp;
