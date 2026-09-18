import { __ } from '@wordpress/i18n';

const HelpTab = () => {
	return (
		<div className="jooosi-icon-tab-content jooosi-icon-help-tab">
			<div className="tab-content-inner">
				<h2>{__('Help & Documentation', 'jooosi-icon')}</h2>
				<p className="description">
					{__('Documentation and help resources coming soon.', 'jooosi-icon')}
				</p>
			</div>
		</div>
	);
};

export default HelpTab;
