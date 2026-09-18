import { __ } from '@wordpress/i18n';

const sponsors = [
	{
		name: 'Jooosi',
		description: __('Open-source tools and products for WordPress.', 'jooosi-icon'),
		href: 'https://jooo.si',
		icon: 'jooosi:jooosi-icon',
	},
	{
		name: 'LiveCanvas',
		description: __('A visual site builder for WordPress.', 'jooosi-icon'),
		href: 'https://livecanvas.com',
		icon: 'jooosi:livecanvas',
	},
	{
		name: __('You, yes you!', 'jooosi-icon'),
		description: __('Your support helps keep this open-source project moving. Thank you!', 'jooosi-icon'),
		href: 'https://github.com/sponsors/suasgn',
		icon: 'lucide:user-star',
	},
];

const AboutTab = () => {
	const version = window.jooosiIconAdmin?.version;
	
	return (
		<div className="jooosi-icon-tab-content jooosi-icon-about-tab">
			<div className="about-hero">
				<div className="hero-icon">
					<jooosi-icon name="jooosi:jooosi-icon" width="64" height="64"></jooosi-icon>
				</div>
				<h1>{__('Jooosi Icon', 'jooosi-icon')}</h1>
				<p className="hero-tagline">
					{__('Enterprise-grade icon management for WordPress', 'jooosi-icon')}
				</p>
				<p className="hero-description">
					{__('A modern WordPress plugin that seamlessly integrates icons across the WordPress ecosystem with support for multiple page builders, custom icon uploads, and access to 200,000+ icons from Iconify.', 'jooosi-icon')}
				</p>
			</div>

			<div className="about-grid">
				<div className="about-card feature-card">
					<div className="card-icon">
						<jooosi-icon name="lucide:database" width="32" height="32"></jooosi-icon>
					</div>
					<h3>{__('Multi-source Icon System', 'jooosi-icon')}</h3>
					<p>{__('Upload custom icons, use bundled icons, or access 200,000+ Iconify icons. Three powerful sources, one unified interface.', 'jooosi-icon')}</p>
				</div>

				<div className="about-card feature-card">
					<div className="card-icon">
						<jooosi-icon name="lucide:zap" width="32" height="32"></jooosi-icon>
					</div>
					<h3>{__('Server-Side Rendering', 'jooosi-icon')}</h3>
					<p>{__('Icons pre-rendered on server for instant display with multi-layer caching (memory, filesystem, IndexedDB) for optimal performance.', 'jooosi-icon')}</p>
				</div>

				<div className="about-card feature-card">
					<div className="card-icon">
						<jooosi-icon name="lucide:code" width="32" height="32"></jooosi-icon>
					</div>
					<h3>{__('Web Component', 'jooosi-icon')}</h3>
					<p>{__('Use the <jooosi-icon> custom element anywhere in your theme or content with attribute reactivity and lazy loading.', 'jooosi-icon')}</p>
				</div>

				<div className="about-card feature-card">
					<div className="card-icon">
						<jooosi-icon name="lucide:shield-check" width="32" height="32"></jooosi-icon>
					</div>
					<h3>{__('Secure & Modern', 'jooosi-icon')}</h3>
					<p>{__('SVG sanitization prevents XSS attacks. Built with PHP 8.2+ attributes, Symfony DI, and auto-discovery architecture.', 'jooosi-icon')}</p>
				</div>
			</div>

			<div className="about-section-divider"></div>

			<div className="about-footer-section">
				<div className="footer-grid">
					<div className="footer-card">
						<div className="footer-card-icon">
							<jooosi-icon name="lucide:github" width="24" height="24"></jooosi-icon>
						</div>
						<div className="footer-card-content">
							<h4>{__('GitHub Repository', 'jooosi-icon')}</h4>
							<p>
								<a href="https://github.com/jooosi-project/jooosi-icon" target="_blank" rel="noopener noreferrer">
									jooosi-project/jooosi-icon
								</a>
							</p>
						</div>
					</div>

					<div className="footer-card">
						<div className="footer-card-icon">
							<jooosi-icon name="lucide:circle-check-big" width="24" height="24"></jooosi-icon>
						</div>
						<div className="footer-card-content">
							<h4>{__('Current Version', 'jooosi-icon')}</h4>
							<p>{version}</p>
						</div>
					</div>

					<div className="footer-card">
						<div className="footer-card-icon">
							<jooosi-icon name="lucide:star" width="24" height="24"></jooosi-icon>
						</div>
						<div className="footer-card-content">
							<h4>{__('Open Source', 'jooosi-icon')}</h4>
							<p>
								<a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener noreferrer">
									GPL-3.0 License
								</a>
							</p>
						</div>
					</div>

					<div className="footer-card">
						<div className="footer-card-icon">
							<jooosi-icon name="lucide:facebook" width="24" height="24"></jooosi-icon>
						</div>
						<div className="footer-card-content">
							<h4>{__('Facebook Community', 'jooosi-icon')}</h4>
							<p>
								<a href="https://www.facebook.com/groups/1142662969627943" target="_blank" rel="noopener noreferrer">
									Join our community
								</a>
							</p>
						</div>
					</div>
				</div>
			</div>

			<div className="about-section-divider"></div>

			<div className="about-support-section">
				<div className="support-header">
					<div className="support-icon">
						<jooosi-icon name="lucide:heart" width="48" height="48"></jooosi-icon>
					</div>
					<h2>{__('Love This Plugin?', 'jooosi-icon')}</h2>
					<p className="support-description">
						{__('Jooosi Icon is 100% free and open source, crafted with passion for the WordPress community. Your sponsorship helps maintain and improve all our free WordPress plugins, not just Jooosi Icon. Supporting one plugin means supporting all our open-source efforts!', 'jooosi-icon')}
					</p>
				</div>

				<div className="support-actions">
					<a
						href="https://github.com/sponsors/suasgn"
						target="_blank"
						rel="noopener noreferrer"
						className="support-button primary github-sponsor"
					>
						<jooosi-icon name="lucide:github" width="24" height="24"></jooosi-icon>
						<div className="button-content">
							<span className="button-label">{__('GitHub Sponsors', 'jooosi-icon')}</span>
						</div>
					</a>

					<a
						href="https://ko-fi.com/Q5Q75XSF7"
						target="_blank"
						rel="noopener noreferrer"
						className="support-button primary kofi-sponsor"
					>
						<jooosi-icon name="simple-icons:kofi" width="24" height="24"></jooosi-icon>
						<div className="button-content">
							<span className="button-label">{__('Support via Ko-fi', 'jooosi-icon')}</span>
						</div>
					</a>
				</div>

				<div className="sponsors-showcase">
					<h3 className="sponsors-title">{__('Proudly Sponsored By', 'jooosi-icon')}</h3>
					<div className="sponsors-grid">
						{sponsors.map((sponsor, index) => (
							<a
								key={sponsor.name}
								href={sponsor.href}
								target="_blank"
								rel="noopener noreferrer"
								className="sponsor-card"
							>
								<div className="sponsor-logo">
									<jooosi-icon name={sponsor.icon} width="56" height="56"></jooosi-icon>
								</div>
								<div className="sponsor-info">
									<h4>{sponsor.name}</h4>
									<p>{sponsor.description}</p>
								</div>
								<jooosi-icon
									name="lucide:external-link"
									width="18"
									height="18"
									className="sponsor-external-link"
								></jooosi-icon>
								{index < sponsors.length - 1 && <span className="sponsor-separator" aria-hidden="true"></span>}
							</a>
						))}
					</div>
				</div>

				<div className="sponsorship-benefits">
					<h3>{__('Sponsorship Benefits', 'jooosi-icon')}</h3>
					<ul>
						<li>
							<div className="benefit-icon">
								<jooosi-icon name="lucide:package" width="18" height="18"></jooosi-icon>
							</div>
							<span>{__('Your brand icon bundled in releases', 'jooosi-icon')}</span>
						</li>
						<li>
							<div className="benefit-icon">
								<jooosi-icon name="lucide:file-text" width="18" height="18"></jooosi-icon>
							</div>
							<span>{__('Logo featured in all plugin READMEs', 'jooosi-icon')}</span>
						</li>
						<li>
							<div className="benefit-icon">
								<jooosi-icon name="lucide:award" width="18" height="18"></jooosi-icon>
							</div>
							<span>{__('Featured in all plugin admin pages', 'jooosi-icon')}</span>
						</li>
						<li>
							<div className="benefit-icon">
								<jooosi-icon name="lucide:users" width="18" height="18"></jooosi-icon>
							</div>
							<span>{__('Exposure to thousands of developers', 'jooosi-icon')}</span>
						</li>
					</ul>
				</div>
			</div>
		</div>
	);
};

export default AboutTab;
