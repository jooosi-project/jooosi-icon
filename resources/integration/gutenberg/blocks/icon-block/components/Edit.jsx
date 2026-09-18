import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

// Import icons from unplugin-icons (Tabler Icons)
import IconSettings from '~icons/tabler/settings';
import IconPalette from '~icons/tabler/palette';
import IconBulb from '~icons/tabler/bulb';
import IconX from '~icons/tabler/x';
import IconSearch from '~icons/tabler/search';

// Import plugin icon
import JooosiIconSvg from '~/jooosi-icon.svg?react';

// Import IconPickerModal
import IconPickerModal from './IconPickerModal';

const Edit = ({ attributes, setAttributes }) => {
	const { name, width, height, color } = attributes;
	const [isModalOpen, setIsModalOpen] = useState(false);

	const blockProps = useBlockProps({});

	// Normalize dimensions for display: if one is undefined, use the other
	const displayWidth = width || height;
	const displayHeight = height || width;

	return (
		<>
			<InspectorControls>
				<div
					className="oiib-panel"
					style={{
						...(color && color !== 'currentColor' ? { '--oiib-primary-color': color } : {})
					}}
				>
					{/* Tab Navigation */}
					<TabPanel
						className="oiib-tab-panel"
						activeClass="is-active"
						tabs={[
							{
								name: 'general',
								title: (
									<span className="oiib-tab-title">
										<IconSettings />
										{__('General', 'jooosi-icon')}
									</span>
								),
								className: 'jooosi-tab-general',
							},
							{
								name: 'style',
								title: (
									<span className="oiib-tab-title">
										<IconPalette />
										{__('Style', 'jooosi-icon')}
									</span>
								),
								className: 'jooosi-tab-style',
							},
						]}
					>
						{(tab) => (
							<div className="oiib-tab-content">
								{tab.name === 'general' && (
									<>
									{/* Icon Selection Card */}
									<div className="oiib-card">
										<div className="oiib-card-header">
											<h4>{__('Icon', 'jooosi-icon')}</h4>
										</div>
										<div className="oiib-card-body">
											<div className="oiib-form-group">
												<label className="oiib-label">
													{__('Icon Name', 'jooosi-icon')} <span className="oiib-required">*</span>
												</label>
													<div className="oiib-input-wrapper">
														<input
															type="text"
															className="oiib-input"
															value={name || ''}
															onChange={(e) => setAttributes({ name: e.target.value })}
															placeholder="mdi:home"
														/>
														<button
															className="oiib-input-icon-search"
															onClick={() => setIsModalOpen(true)}
															title={__('Browse icons', 'jooosi-icon')}
														>
															<IconSearch style={{ width: '20px', height: '20px' }} />
														</button>
													</div>
													<p className="oiib-help-text">
														<IconBulb />
														{__('Format: prefix:name (e.g., mdi:home, fa:github, lucide:star)', 'jooosi-icon')}
													</p>
												</div>

												{/* Icon Preview */}
												{name && (
													<div className="oiib-preview-card">
														<div className="oiib-preview-content">
															<jooosi-icon
																name={name}
																width="48"
																height="48"
																{...(color && { color })}
															/>
														</div>
														<div className="oiib-preview-label">
															{name}
														</div>
													</div>
												)}
											</div>
										</div>
									</>
								)}

								{tab.name === 'style' && (
									<>
										{/* Size Card */}
										<div className="oiib-card">
											<div className="oiib-card-header">
												<h4>{__('Dimensions', 'jooosi-icon')}</h4>
												<button
													className={`oiib-reset-btn ${(!width && !height) ? 'jooosi-reset-btn-disabled' : ''}`}
													onClick={() => setAttributes({ width: undefined, height: undefined })}
													disabled={!width && !height}
												>
													{__('Reset', 'jooosi-icon')}
												</button>
											</div>
											<div className="oiib-card-body">
												{/* Width */}
												<div className="oiib-form-group">
													<div className="oiib-label-row">
														<label className="oiib-label">
															{__('Width', 'jooosi-icon')}
														</label>
														<div className="oiib-label-row-actions">
															<div className="oiib-dimension-wrapper">
																<input
																	type="number"
																	className="oiib-dimension-input"
																	value={parseInt(width) || ''}
																	onChange={(e) => {
																		const val = parseInt(e.target.value);
																		if (!isNaN(val) && val >= 16 && val <= 256) {
																			setAttributes({ width: e.target.value });
																		} else if (e.target.value === '') {
																			setAttributes({ width: undefined });
																		}
																	}}
																	min="16"
																	max="256"
																	placeholder="auto"
																/>
																<span className="oiib-dimension-unit">px</span>
															</div>
															<button
																className={`oiib-clear-btn ${!width ? 'oiib-clear-btn-disabled' : ''}`}
																onClick={() => setAttributes({ width: undefined })}
																title={__('Reset to original', 'jooosi-icon')}
																disabled={!width}
															>
																<IconX />
															</button>
														</div>
													</div>
													<div className="oiib-slider-control">
														<input
															type="range"
															className="oiib-slider"
															value={parseInt(width) || parseInt(height) || 24}
															onChange={(e) => setAttributes({ width: e.target.value })}
															min="16"
															max="256"
														/>
													</div>
												</div>

												{/* Height */}
												<div className="oiib-form-group">
													<div className="oiib-label-row">
														<label className="oiib-label">
															{__('Height', 'jooosi-icon')}
														</label>
														<div className="oiib-label-row-actions">
															<div className="oiib-dimension-wrapper">
																<input
																	type="number"
																	className="oiib-dimension-input"
																	value={parseInt(height) || ''}
																	onChange={(e) => {
																		const val = parseInt(e.target.value);
																		if (!isNaN(val) && val >= 16 && val <= 256) {
																			setAttributes({ height: e.target.value });
																		} else if (e.target.value === '') {
																			setAttributes({ height: undefined });
																		}
																	}}
																	min="16"
																	max="256"
																	placeholder="auto"
																/>
																<span className="oiib-dimension-unit">px</span>
															</div>
															<button
																className={`oiib-clear-btn ${!height ? 'oiib-clear-btn-disabled' : ''}`}
																onClick={() => setAttributes({ height: undefined })}
																title={__('Reset to original', 'jooosi-icon')}
																disabled={!height}
															>
																<IconX />
															</button>
														</div>
													</div>
													<div className="oiib-slider-control">
														<input
															type="range"
															className="oiib-slider"
															value={parseInt(height) || parseInt(width) || 24}
															onChange={(e) => setAttributes({ height: e.target.value })}
															min="16"
															max="256"
														/>
													</div>
												</div>
											</div>
										</div>

										{/* Color Card */}
										<div className="oiib-card">
											<div className="oiib-card-header">
												<h4>{__('Color', 'jooosi-icon')}</h4>
												<button
													className={`oiib-reset-btn ${(!color || color === 'currentColor') ? 'jooosi-reset-btn-disabled' : ''}`}
													onClick={() => setAttributes({ color: 'currentColor' })}
													disabled={!color || color === 'currentColor'}
												>
													{__('Reset', 'jooosi-icon')}
												</button>
											</div>
											<div className="oiib-card-body">
												<div className="oiib-color-input-wrapper">
													<div className="oiib-color-swatch-container">
														<input
															type="color"
															className="oiib-color-swatch-picker"
															value={color && color !== 'currentColor' ? color : '#000000'}
															onChange={(e) => setAttributes({ color: e.target.value })}
															title={__('Pick a color', 'jooosi-icon')}
														/>
														<div
															className="oiib-color-swatch"
															style={{ backgroundColor: color && color !== 'currentColor' ? color : 'currentColor' }}
														/>
													</div>
													<input
														type="text"
														className="oiib-color-value-input"
														value={color && color !== 'currentColor' ? color : 'currentColor'}
														onChange={(e) => setAttributes({ color: e.target.value })}
														placeholder="#000000"
													/>
												</div>
											</div>
										</div>
									</>
								)}
							</div>
						)}
					</TabPanel>
				</div>
			</InspectorControls>

			<div {...blockProps}>
				{!name ? (
					<div className="oiib-placeholder" onClick={() => setIsModalOpen(true)} style={{ cursor: 'pointer' }}>
						<div className="oiib-placeholder-icon">
							<JooosiIconSvg width={40} height={40} aria-hidden="true" focusable="false" />
						</div>
						<div className="oiib-placeholder-content">
							<h4>{__('Jooosi Icon', 'jooosi-icon')}</h4>
							<p>{__('Click to browse icons or use the sidebar to configure', 'jooosi-icon')}</p>
						</div>
						<div className="oiib-placeholder-footer">
							<span className="oiib-placeholder-hint">
								<IconBulb style={{ width: '14px', height: '14px' }} />
								{__('Format: prefix:name (e.g., mdi:home)', 'jooosi-icon')}
							</span>
						</div>
					</div>
				) : (
					<jooosi-icon
						name={name}
						{...(displayWidth && { width: displayWidth })}
						{...(displayHeight && { height: displayHeight })}
						{...(color && { color })}
					/>
				)}
			</div>

			{/* Icon Picker Modal */}
			<IconPickerModal
				isOpen={isModalOpen}
				onClose={() => setIsModalOpen(false)}
				onSelectIcon={(iconName) => setAttributes({ name: iconName })}
				currentIcon={name}
			/>
		</>
	);
};

export default Edit;
