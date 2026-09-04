import { useEffect, useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	getIntegrations,
	applySiteKitRecommended,
	startGoogleAnalyticsOAuth,
	completeGoogleAnalyticsOAuth,
	disconnectGoogleAnalyticsOAuth,
	getGoogleAnalyticsProperties,
	selectGoogleAnalyticsProperty,
	startGoogleTagManagerOAuth,
	completeGoogleTagManagerOAuth,
	disconnectGoogleTagManagerOAuth,
	getGoogleTagManagerContainers,
	selectGoogleTagManagerContainer,
} from '../api';
import {
	LoadingState,
	ErrorState,
	PageHeader,
	SaveFooter,
	FormPage,
	isFormDirty,
	StatusBadge,
	DetailBackLink,
	Tabs,
	Alert,
	useNotify,
} from '../components/ui';
import { getQueryParam, setQueryParam } from '../url-state';

const NS = 'complyops/v1';
const canManage = !! window.complyopsAdmin?.canManage;

function obfuscateTokenPreview( token ) {
	const value = String( token || '' ).trim();

	if ( ! value ) {
		return '';
	}

	if ( value.length <= 6 ) {
		return '•'.repeat( value.length );
	}

	return (
		value.slice( 0, 3 ) + '•'.repeat( value.length - 6 ) + value.slice( -3 )
	);
}

const PACK_DEFINITIONS = [
	{
		id: 'google-analytics',
		label: __( 'Google Analytics', 'complyops' ),
		subtitle: __(
			'GA4 measurement IDs and consent enforcement',
			'complyops'
		),
		hasConfig: true,
	},
	{
		id: 'google-tag-manager',
		label: __( 'Google Tag Manager', 'complyops' ),
		subtitle: __( 'GTM container IDs and script blocking', 'complyops' ),
		hasConfig: true,
	},
	{
		id: 'youtube',
		label: __( 'YouTube Embeds', 'complyops' ),
		subtitle: __( 'Embed gating and placeholder messaging', 'complyops' ),
		hasConfig: true,
	},
	{
		id: 'browser-verification',
		label: __( 'Hosted browser verification', 'complyops' ),
		subtitle: __(
			'Remote Chromium service for runtime control checks',
			'complyops'
		),
		hasConfig: true,
	},
	{
		id: 'forms',
		label: __( 'Forms', 'complyops' ),
		subtitle: __( 'Form plugin inventory and field analysis', 'complyops' ),
		hasConfig: false,
	},
	{
		id: 'other-services',
		label: __( 'Other Detected Services', 'complyops' ),
		subtitle: __(
			'Additional third-party integrations discovered on the site',
			'complyops'
		),
		hasConfig: false,
	},
	{
		id: 'third-party',
		label: __( 'Third-Party Review', 'complyops' ),
		subtitle: __( 'Unknown hosts flagged for manual review', 'complyops' ),
		hasConfig: false,
	},
	{
		id: 'consent-providers',
		label: __( 'Consent Providers', 'complyops' ),
		subtitle: __(
			'Third-party consent managers detected on the site',
			'complyops'
		),
		hasConfig: false,
	},
];

const PRIMARY_INTEGRATION_IDS = new Set( [
	'google_analytics',
	'google_tag_manager',
	'youtube',
] );

function mergeMeasurementIds( existing = [], manualId = '' ) {
	const ids = Array.isArray( existing ) ? [ ...existing ] : [];
	const normalized = String( manualId || '' )
		.trim()
		.toUpperCase();

	if ( normalized && ! ids.includes( normalized ) ) {
		ids.push( normalized );
	}

	return ids;
}

function buildPackSummaries( data, enforcement, browserVerificationSettings ) {
	const gaDetail = data?.google_analytics || {};
	const manualGa = gaDetail.manual || {};
	const gtmDetail = data?.google_tag_manager || {};
	const siteKit = data?.site_kit || {};
	const integrations = data?.integrations || {};
	const formInventory = data?.forms?.inventory?.forms || [];
	const formPlugins = data?.forms?.plugins || {};
	const thirdParty = data?.third_party || {};
	const consentProviders = data?.consent?.providers || {};
	const otherDetected = Object.entries( integrations ).filter(
		( [ id, item ] ) => item.detected && ! PRIMARY_INTEGRATION_IDS.has( id )
	);

	return PACK_DEFINITIONS.map( ( pack ) => {
		let detected = false;
		let detail = '';

		switch ( pack.id ) {
			case 'google-analytics':
				detected =
					!! integrations.google_analytics?.detected ||
					( gaDetail.measurement_ids?.length || 0 ) > 0 ||
					!! siteKit.analytics?.connected ||
					!! manualGa.configured ||
					!! manualGa.oauth?.connected;
				detail = manualGa.oauth?.connected
					? manualGa.oauth?.property_selected
						? __( 'Google account', 'complyops' )
						: __( 'Choose property', 'complyops' )
					: siteKit.analytics?.connected
					? __( 'Site Kit', 'complyops' )
					: manualGa.configured
					? __( 'Manual', 'complyops' )
					: gaDetail.measurement_ids?.length
					? `${ gaDetail.measurement_ids.length } ${ __(
							'IDs',
							'complyops'
					  ) }`
					: '';
				break;
			case 'google-tag-manager':
				const gtmManual = data?.google_tag_manager?.manual || {};
				detected =
					!! integrations.google_tag_manager?.detected ||
					( gtmDetail.container_ids?.length || 0 ) > 0 ||
					!! siteKit.tag_manager?.connected ||
					!! gtmManual.configured ||
					!! gtmManual.oauth?.connected;
				detail = gtmManual.oauth?.connected
					? gtmManual.oauth?.container_selected
						? __( 'Google account', 'complyops' )
						: __( 'Choose container', 'complyops' )
					: siteKit.tag_manager?.connected
					? __( 'Site Kit', 'complyops' )
					: gtmManual.configured
					? __( 'Manual', 'complyops' )
					: gtmDetail.container_ids?.length
					? `${ gtmDetail.container_ids.length } ${ __(
							'containers',
							'complyops'
					  ) }`
					: '';
				break;
			case 'youtube':
				detected = !! integrations.youtube?.detected;
				detail = integrations.youtube?.sources?.join( ', ' ) || '';
				break;
			case 'browser-verification':
				const browserVerification = data?.browser_verification || {};
				const browserSettings = browserVerification.settings || {};
				detected =
					!! browserVerification.available ||
					!! browserSettings.local_available;
				detail = browserVerification.available
					? browserVerification.mode === 'remote'
						? __( 'Remote service', 'complyops' )
						: __( 'Local Chromium', 'complyops' )
					: browserSettings.configured
					? __( 'Configured', 'complyops' )
					: '';
				break;
			case 'forms':
				detected =
					formInventory.length > 0 ||
					Object.values( formPlugins ).some(
						( item ) => item.detected
					);
				detail = formInventory.length
					? `${ formInventory.length } ${ __(
							'forms',
							'complyops'
					  ) }`
					: '';
				break;
			case 'other-services':
				detected = otherDetected.length > 0;
				detail = otherDetected.length
					? `${ otherDetected.length } ${ __(
							'services',
							'complyops'
					  ) }`
					: '';
				break;
			case 'third-party':
				detected = ( thirdParty.review_items || [] ).length > 0;
				detail = detected
					? `${ thirdParty.review_items.length } ${ __(
							'hosts',
							'complyops'
					  ) }`
					: '';
				break;
			case 'consent-providers':
				detected = Object.values( consentProviders ).some(
					( item ) => item.detected
				);
				detail = detected ? __( 'CMP detected', 'complyops' ) : '';
				break;
			default:
				break;
		}

		let configStatus = null;
		if ( pack.hasConfig ) {
			if ( pack.id === 'google-analytics' ) {
				configStatus = enforcement?.enabled ? 'PASS' : 'UNKNOWN';
			} else if ( pack.id === 'google-tag-manager' ) {
				configStatus =
					enforcement?.enabled &&
					enforcement?.block_gtm_before_consent
						? 'PASS'
						: 'UNKNOWN';
			} else if ( pack.id === 'youtube' ) {
				configStatus = enforcement?.gate_youtube_before_consent
					? 'PASS'
					: 'UNKNOWN';
			} else if ( pack.id === 'browser-verification' ) {
				configStatus =
					browserVerificationSettings?.enabled &&
					browserVerificationSettings?.configured
						? 'PASS'
						: 'UNKNOWN';
			}
		}

		return {
			...pack,
			detected,
			detail,
			configStatus,
		};
	} );
}

function ConfigCheckbox( { label, checked, onChange, disabled } ) {
	return (
		<label>
			<input
				type="checkbox"
				checked={ !! checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
				disabled={ disabled }
			/>{ ' ' }
			{ label }
		</label>
	);
}

function SiteKitStatusPanel( { siteKit, service = 'analytics' } ) {
	if ( ! siteKit?.plugin_active ) {
		return null;
	}

	const serviceData =
		service === 'tag_manager' ? siteKit.tag_manager : siteKit.analytics;
	const consentMode = siteKit.consent_mode || {};

	if ( ! serviceData?.module_active && ! serviceData?.connected ) {
		return (
			<div className="complyops-panel complyops-panel--tint">
				<h2>{ __( 'Google Site Kit', 'complyops' ) }</h2>
				<p className="complyops-muted">
					{ __(
						'Site Kit is installed, but this service is not connected yet.',
						'complyops'
					) }
				</p>
			</div>
		);
	}

	return (
		<div className="complyops-panel complyops-panel--tint">
			<h2>{ __( 'Google Site Kit', 'complyops' ) }</h2>
			<ul className="complyops-list">
				<li>
					<strong>{ __( 'Status', 'complyops' ) }</strong>
					<span className="complyops-muted">
						{ ' ' }
						—{ ' ' }
						{ serviceData?.connected
							? __( 'Connected', 'complyops' )
							: __( 'Module active', 'complyops' ) }
					</span>
				</li>
				{ service === 'analytics' && serviceData?.measurement_id && (
					<li>
						<strong>{ __( 'Measurement ID', 'complyops' ) }</strong>
						<span className="complyops-muted">
							{ ' ' }
							— <code>{ serviceData.measurement_id }</code>
						</span>
					</li>
				) }
				{ service === 'tag_manager' && serviceData?.container_id && (
					<li>
						<strong>{ __( 'Container ID', 'complyops' ) }</strong>
						<span className="complyops-muted">
							{ ' ' }
							— <code>{ serviceData.container_id }</code>
						</span>
					</li>
				) }
				<li>
					<strong>{ __( 'Places snippet', 'complyops' ) }</strong>
					<span className="complyops-muted">
						{ ' ' }
						—{ ' ' }
						{ serviceData?.use_snippet
							? __( 'Yes', 'complyops' )
							: __( 'No', 'complyops' ) }
					</span>
				</li>
				{ service === 'analytics' && (
					<li>
						<strong>
							{ __( 'Site Kit Consent Mode', 'complyops' ) }
						</strong>
						<span className="complyops-muted">
							{ ' ' }
							—{ ' ' }
							{ consentMode.enabled
								? __( 'Enabled', 'complyops' )
								: __( 'Disabled', 'complyops' ) }
						</span>
					</li>
				) }
			</ul>
		</div>
	);
}

function SiteKitRecommendationPanel( {
	recommendation,
	applying,
	onApply,
	readOnly,
} ) {
	if ( ! recommendation?.applicable ) {
		return null;
	}

	return (
		<div className="complyops-panel">
			<h2>{ __( 'Site Kit recommendations', 'complyops' ) }</h2>
			<ul className="complyops-list">
				{ ( recommendation.reasons || [] ).map( ( reason ) => (
					<li key={ reason }>{ reason }</li>
				) ) }
			</ul>
			{ recommendation.has_changes && ! readOnly && (
				<p>
					<button
						type="button"
						className="button button-secondary"
						onClick={ onApply }
						disabled={ applying }
					>
						{ applying
							? __( 'Applying…', 'complyops' )
							: __(
									'Apply recommended enforcement',
									'complyops'
							  ) }
					</button>
				</p>
			) }
			{ ! recommendation.has_changes && (
				<p className="complyops-muted">
					{ __(
						'ComplyOps enforcement already matches the Site Kit recommendation.',
						'complyops'
					) }
				</p>
			) }
		</div>
	);
}

function PackListView( { packs, loading, error, onRetry, onOpenPack } ) {
	if ( loading && ! packs.length ) {
		return (
			<LoadingState
				message={ __( 'Scanning integrations…', 'complyops' ) }
			/>
		);
	}

	if ( error && ! packs.length ) {
		return <ErrorState message={ error } onRetry={ onRetry } />;
	}

	return (
		<>
			<PageHeader
				eyebrow={ __( 'Manage', 'complyops' ) }
				title={ __( 'Integrations', 'complyops' ) }
				description={ __(
					'Discover connected services, review data collection surfaces, and configure consent enforcement.',
					'complyops'
				) }
			/>

			{ error && (
				<div
					className="complyops-alert complyops-alert--danger"
					role="alert"
				>
					{ error }
				</div>
			) }

			<div className="complyops-panel complyops-panel--flush">
				<div className="complyops-table-wrap">
					<table className="widefat striped complyops-table">
						<thead>
							<tr>
								<th>{ __( 'Integration', 'complyops' ) }</th>
								<th>{ __( 'Detected', 'complyops' ) }</th>
								<th>{ __( 'Enforcement', 'complyops' ) }</th>
								<th>{ __( 'Actions', 'complyops' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ packs.map( ( pack ) => (
								<tr key={ pack.id }>
									<td>
										<strong>{ pack.label }</strong>
										<div className="complyops-muted">
											{ pack.subtitle }
										</div>
									</td>
									<td>
										<StatusBadge
											status={
												pack.detected
													? 'PASS'
													: 'UNKNOWN'
											}
											label={
												pack.detected
													? __(
															'Detected',
															'complyops'
													  )
													: __(
															'Not detected',
															'complyops'
													  )
											}
										/>
										{ pack.detail && (
											<div className="complyops-muted">
												{ pack.detail }
											</div>
										) }
									</td>
									<td>
										{ pack.hasConfig ? (
											<StatusBadge
												status={ pack.configStatus }
												label={
													pack.configStatus === 'PASS'
														? __(
																'Active',
																'complyops'
														  )
														: __(
																'Inactive',
																'complyops'
														  )
												}
											/>
										) : (
											<span className="complyops-muted">
												—
											</span>
										) }
									</td>
									<td className="complyops-table__actions">
										<button
											type="button"
											className="button button-link"
											onClick={ () =>
												onOpenPack( pack.id )
											}
										>
											{ pack.hasConfig
												? __(
														'View & configure',
														'complyops'
												  )
												: __(
														'View details',
														'complyops'
												  ) }
										</button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			</div>
		</>
	);
}

function GoogleAnalyticsPropertyPicker( {
	oauth,
	readOnly,
	changing = false,
	onCancel,
	onPropertyAttached,
} ) {
	const [ properties, setProperties ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ loadError, setLoadError ] = useState( null );
	const [ selectedId, setSelectedId ] = useState( oauth?.property_id || '' );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		setSelectedId( oauth?.property_id || '' );
	}, [ oauth?.property_id ] );

	useEffect( () => {
		if ( ! oauth?.connected ) {
			return;
		}

		let cancelled = false;

		( async () => {
			setLoading( true );
			setLoadError( null );

			try {
				const response = await getGoogleAnalyticsProperties();
				if ( cancelled ) {
					return;
				}
				setProperties( response.properties || [] );
			} catch ( err ) {
				if ( ! cancelled ) {
					setLoadError(
						err?.message ||
							__(
								'Could not load Google Analytics properties.',
								'complyops'
							)
					);
				}
			} finally {
				if ( ! cancelled ) {
					setLoading( false );
				}
			}
		} )();

		return () => {
			cancelled = true;
		};
	}, [ oauth?.connected ] );

	const attachProperty = async () => {
		if ( ! selectedId || readOnly ) {
			return;
		}

		setSaving( true );
		setLoadError( null );

		try {
			const response = await selectGoogleAnalyticsProperty( selectedId );
			onPropertyAttached( response );
		} catch ( err ) {
			setLoadError(
				err?.message ||
					__( 'Could not attach the selected property.', 'complyops' )
			);
		} finally {
			setSaving( false );
		}
	};

	const selectionDirty =
		!! selectedId && selectedId !== ( oauth?.property_id || '' );

	return (
		<div className="complyops-ga-property-picker">
			{ ! changing && (
				<p className="complyops-muted">
					{ __(
						'Choose which Google Analytics property this WordPress site should use.',
						'complyops'
					) }
				</p>
			) }

			{ oauth?.needs_property_selection && (
				<Alert tone="warning">
					{ __(
						'Select a property to finish connecting Google Analytics.',
						'complyops'
					) }
				</Alert>
			) }

			{ loadError && <Alert tone="danger">{ loadError }</Alert> }

			{ loading ? (
				<p className="complyops-muted">
					{ __( 'Loading properties…', 'complyops' ) }
				</p>
			) : properties.length ? (
				<ul className="complyops-choice-list">
					{ properties.map( ( property ) => {
						const inputId = `complyops-ga-property-${ property.property_id }`;

						return (
							<li key={ property.property_id }>
								<label
									htmlFor={ inputId }
									className="complyops-choice-list__option"
								>
									<input
										id={ inputId }
										type="radio"
										name="complyops_ga_property"
										value={ property.property_id }
										checked={
											selectedId === property.property_id
										}
										onChange={ () =>
											setSelectedId(
												property.property_id
											)
										}
										disabled={ readOnly || saving }
									/>
									<span className="complyops-choice-list__content">
										<strong>
											{ property.property_display_name ||
												property.property_id }
										</strong>
										<span className="complyops-muted">
											{ property.account_display_name ||
												__(
													'Google Analytics account',
													'complyops'
												) }
											{ ' · ' }
											<code>
												{ property.measurement_id }
											</code>
										</span>
									</span>
								</label>
							</li>
						);
					} ) }
				</ul>
			) : (
				! loadError && (
					<p className="complyops-muted">
						{ __(
							'No GA4 properties were found for this Google account.',
							'complyops'
						) }
					</p>
				)
			) }

			{ ! readOnly &&
				selectedId &&
				( oauth?.needs_property_selection || selectionDirty ) && (
					<p>
						<button
							type="button"
							className="button button-primary"
							onClick={ attachProperty }
							disabled={ saving || loading }
						>
							{ saving
								? __( 'Attaching…', 'complyops' )
								: __( 'Attach property', 'complyops' ) }
						</button>
					</p>
				) }

			{ changing && onCancel && ! readOnly && (
				<p>
					<button
						type="button"
						className="button button-link"
						onClick={ () => {
							setSelectedId( oauth?.property_id || '' );
							setLoadError( null );
							onCancel();
						} }
					>
						{ __( 'Cancel', 'complyops' ) }
					</button>
				</p>
			) }
		</div>
	);
}

function GoogleAnalyticsManualSettings( {
	manual,
	readOnly,
	onUpdateGaSettings,
} ) {
	return (
		<>
			<p className="complyops-muted">
				{ __(
					'Optional fallback when Site Kit or Google sign-in is not used.',
					'complyops'
				) }
			</p>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Google account email', 'complyops' ) }
						</th>
						<td>
							<input
								type="email"
								className="regular-text"
								value={ manual.account_email || '' }
								onChange={ ( event ) =>
									onUpdateGaSettings(
										'account_email',
										event.target.value
									)
								}
								disabled={ readOnly }
								placeholder="you@company.com"
								autoComplete="off"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Measurement ID', 'complyops' ) }
						</th>
						<td>
							<input
								type="text"
								className="regular-text"
								value={ manual.measurement_id || '' }
								onChange={ ( event ) =>
									onUpdateGaSettings(
										'measurement_id',
										event.target.value.toUpperCase()
									)
								}
								disabled={ readOnly }
								placeholder="G-XXXXXXXXXX"
								autoComplete="off"
							/>
							<p className="complyops-muted">
								{ __(
									'Your GA4 measurement ID (starts with G-).',
									'complyops'
								) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Property ID', 'complyops' ) }
						</th>
						<td>
							<input
								type="text"
								className="regular-text"
								value={ manual.property_id || '' }
								onChange={ ( event ) =>
									onUpdateGaSettings(
										'property_id',
										event.target.value.replace( /\D/g, '' )
									)
								}
								disabled={ readOnly }
								placeholder="123456789"
								autoComplete="off"
							/>
							<p className="complyops-muted">
								{ __(
									'Numeric GA4 property ID from Google Analytics Admin.',
									'complyops'
								) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			{ manual.configured && (
				<p className="complyops-muted">
					<StatusBadge
						status="PASS"
						label={ __(
							'Manual connection configured',
							'complyops'
						) }
					/>
				</p>
			) }
		</>
	);
}

function GoogleAnalyticsConnectionCard( {
	oauth,
	manual,
	readOnly,
	connecting,
	disconnecting,
	onConnect,
	onDisconnect,
	onUpdateGaSettings,
	onPropertyAttached,
} ) {
	const [ activeTab, setActiveTab ] = useState( 'google' );
	const [ isChangingProperty, setIsChangingProperty ] = useState( false );

	const isOAuthConnected = !! oauth?.connected;
	const isFullyConnected = isOAuthConnected && !! oauth?.property_selected;

	useEffect( () => {
		if ( isFullyConnected ) {
			setIsChangingProperty( false );
		}
	}, [ isFullyConnected, oauth?.property_id ] );

	const handlePropertyAttached = ( response ) => {
		onPropertyAttached( response );
		setIsChangingProperty( false );
	};

	if ( isFullyConnected && ! isChangingProperty ) {
		return (
			<div className="complyops-panel complyops-panel--tint complyops-ga-connection-status">
				<h2>{ __( 'Connection status', 'complyops' ) }</h2>
				<ul className="complyops-list">
					<li>
						<div className="complyops-ga-property-summary__row">
							<strong>{ __( 'Property', 'complyops' ) }</strong>
							<span className="complyops-ga-property-summary__value">
								<span className="complyops-muted">
									{ oauth.property_display_name ||
										oauth.property_id }
								</span>
								{ ! readOnly && (
									<button
										type="button"
										className="button button-link complyops-ga-property-summary__change"
										onClick={ () =>
											setIsChangingProperty( true )
										}
									>
										{ __( 'Change', 'complyops' ) }
									</button>
								) }
							</span>
						</div>
					</li>
					<li>
						<strong>{ __( 'Measurement ID', 'complyops' ) }</strong>
						<span className="complyops-muted">
							{ ' ' }
							<code>{ oauth.measurement_id }</code>
						</span>
					</li>
					<li>
						<strong>{ __( 'Status', 'complyops' ) }</strong>
						<span>
							{ ' ' }
							<StatusBadge
								status="PASS"
								label={ __( 'Connected', 'complyops' ) }
							/>
						</span>
					</li>
				</ul>
				{ ! readOnly && (
					<p>
						<button
							type="button"
							className="button button-secondary"
							onClick={ onDisconnect }
							disabled={ disconnecting }
						>
							{ disconnecting
								? __( 'Disconnecting…', 'complyops' )
								: __( 'Disconnect', 'complyops' ) }
						</button>
					</p>
				) }
			</div>
		);
	}

	if ( isOAuthConnected ) {
		return (
			<div className="complyops-panel complyops-panel--tint">
				<h2>
					{ isChangingProperty
						? __( 'Change property', 'complyops' )
						: __( 'Connection', 'complyops' ) }
				</h2>
				{ ! isChangingProperty && (
					<ul className="complyops-list">
						<li>
							<strong>{ __( 'Account', 'complyops' ) }</strong>
							<span className="complyops-muted">
								{ ' ' }
								{ oauth.account_email ||
									__( 'Connected', 'complyops' ) }
							</span>
						</li>
					</ul>
				) }
				<GoogleAnalyticsPropertyPicker
					oauth={ oauth }
					readOnly={ readOnly }
					changing={ isChangingProperty }
					onCancel={
						isChangingProperty
							? () => setIsChangingProperty( false )
							: undefined
					}
					onPropertyAttached={ handlePropertyAttached }
				/>
				{ ! isChangingProperty && ! readOnly && (
					<p>
						<button
							type="button"
							className="button button-secondary"
							onClick={ onDisconnect }
							disabled={ disconnecting }
						>
							{ disconnecting
								? __( 'Disconnecting…', 'complyops' )
								: __( 'Disconnect', 'complyops' ) }
						</button>
					</p>
				) }
			</div>
		);
	}

	const connectionTabs = [
		{ value: 'google', label: __( 'Google sign-in', 'complyops' ) },
		{ value: 'manual', label: __( 'Manual', 'complyops' ) },
	];

	return (
		<div className="complyops-panel">
			<h2>{ __( 'Connection', 'complyops' ) }</h2>
			<Tabs
				items={ connectionTabs }
				active={ activeTab }
				onChange={ setActiveTab }
			/>

			{ activeTab === 'google' && (
				<div role="tabpanel">
					<p className="complyops-muted">
						{ __(
							'Sign in with Google and allow access so ComplyOps can identify your GA4 property and use the Analytics Admin API.',
							'complyops'
						) }
					</p>
					{ ! readOnly && (
						<p>
							<button
								type="button"
								className="button button-primary"
								onClick={ onConnect }
								disabled={ connecting }
							>
								{ connecting
									? __(
											'Redirecting to Google…',
											'complyops'
									  )
									: __(
											'Configure with Google',
											'complyops'
									  ) }
							</button>
						</p>
					) }
				</div>
			) }

			{ activeTab === 'manual' && (
				<div role="tabpanel">
					<GoogleAnalyticsManualSettings
						manual={ manual }
						readOnly={ readOnly }
						onUpdateGaSettings={ onUpdateGaSettings }
					/>
				</div>
			) }
		</div>
	);
}

function GoogleTagManagerContainerPicker( {
	oauth,
	readOnly,
	changing = false,
	onCancel,
	onContainerAttached,
} ) {
	const [ containers, setContainers ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ loadError, setLoadError ] = useState( null );
	const [ selectedId, setSelectedId ] = useState( oauth?.container_id || '' );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		setSelectedId( oauth?.container_id || '' );
	}, [ oauth?.container_id ] );

	useEffect( () => {
		if ( ! oauth?.connected ) {
			return;
		}

		let cancelled = false;

		( async () => {
			setLoading( true );
			setLoadError( null );

			try {
				const response = await getGoogleTagManagerContainers();
				if ( cancelled ) {
					return;
				}
				setContainers( response.containers || [] );
			} catch ( err ) {
				if ( ! cancelled ) {
					setLoadError(
						err?.message ||
							__(
								'Could not load Google Tag Manager containers.',
								'complyops'
							)
					);
				}
			} finally {
				if ( ! cancelled ) {
					setLoading( false );
				}
			}
		} )();

		return () => {
			cancelled = true;
		};
	}, [ oauth?.connected ] );

	const attachContainer = async () => {
		if ( ! selectedId || readOnly ) {
			return;
		}

		setSaving( true );
		setLoadError( null );

		try {
			const response =
				await selectGoogleTagManagerContainer( selectedId );
			onContainerAttached( response );
		} catch ( err ) {
			setLoadError(
				err?.message ||
					__(
						'Could not attach the selected container.',
						'complyops'
					)
			);
		} finally {
			setSaving( false );
		}
	};

	const selectionDirty =
		!! selectedId && selectedId !== ( oauth?.container_id || '' );

	return (
		<div className="complyops-ga-property-picker">
			{ ! changing && (
				<p className="complyops-muted">
					{ __(
						'Choose which Google Tag Manager container this WordPress site should use.',
						'complyops'
					) }
				</p>
			) }

			{ oauth?.needs_container_selection && (
				<Alert tone="warning">
					{ __(
						'Select a container to finish connecting Google Tag Manager.',
						'complyops'
					) }
				</Alert>
			) }

			{ loadError && <Alert tone="danger">{ loadError }</Alert> }

			{ loading ? (
				<p className="complyops-muted">
					{ __( 'Loading containers…', 'complyops' ) }
				</p>
			) : containers.length ? (
				<ul className="complyops-choice-list">
					{ containers.map( ( container ) => {
						const inputId = `complyops-gtm-container-${ container.container_id }`;

						return (
							<li key={ container.container_id }>
								<label
									htmlFor={ inputId }
									className="complyops-choice-list__option"
								>
									<input
										id={ inputId }
										type="radio"
										name="complyops_gtm_container"
										value={ container.container_id }
										checked={
											selectedId ===
											container.container_id
										}
										onChange={ () =>
											setSelectedId(
												container.container_id
											)
										}
										disabled={ readOnly || saving }
									/>
									<span className="complyops-choice-list__content">
										<strong>
											{ container.container_display_name ||
												container.container_id }
										</strong>
										<span className="complyops-muted">
											{ container.account_display_name ||
												__(
													'Google Tag Manager account',
													'complyops'
												) }
											{ ' · ' }
											<code>
												{ container.container_id }
											</code>
										</span>
									</span>
								</label>
							</li>
						);
					} ) }
				</ul>
			) : (
				! loadError && (
					<p className="complyops-muted">
						{ __(
							'No GTM containers were found for this Google account.',
							'complyops'
						) }
					</p>
				)
			) }

			{ ! readOnly &&
				selectedId &&
				( oauth?.needs_container_selection || selectionDirty ) && (
					<p>
						<button
							type="button"
							className="button button-primary"
							onClick={ attachContainer }
							disabled={ saving || loading }
						>
							{ saving
								? __( 'Attaching…', 'complyops' )
								: __( 'Attach container', 'complyops' ) }
						</button>
					</p>
				) }

			{ changing && onCancel && ! readOnly && (
				<p>
					<button
						type="button"
						className="button button-link"
						onClick={ () => {
							setSelectedId( oauth?.container_id || '' );
							setLoadError( null );
							onCancel();
						} }
					>
						{ __( 'Cancel', 'complyops' ) }
					</button>
				</p>
			) }
		</div>
	);
}

function GoogleTagManagerConnectionCard( {
	oauth,
	readOnly,
	connecting,
	disconnecting,
	onConnect,
	onDisconnect,
	onContainerAttached,
} ) {
	const [ isChangingContainer, setIsChangingContainer ] = useState( false );

	const isOAuthConnected = !! oauth?.connected;
	const isFullyConnected = isOAuthConnected && !! oauth?.container_selected;

	useEffect( () => {
		if ( isFullyConnected ) {
			setIsChangingContainer( false );
		}
	}, [ isFullyConnected, oauth?.container_id ] );

	const handleContainerAttached = ( response ) => {
		onContainerAttached( response );
		setIsChangingContainer( false );
	};

	if ( isFullyConnected && ! isChangingContainer ) {
		return (
			<div className="complyops-panel complyops-panel--tint complyops-ga-connection-status">
				<h2>{ __( 'Connection status', 'complyops' ) }</h2>
				<ul className="complyops-list">
					<li>
						<div className="complyops-ga-property-summary__row">
							<strong>{ __( 'Container', 'complyops' ) }</strong>
							<span className="complyops-ga-property-summary__value">
								<span className="complyops-muted">
									{ oauth.container_display_name ||
										oauth.container_id }
								</span>
								{ ! readOnly && (
									<button
										type="button"
										className="button button-link complyops-ga-property-summary__change"
										onClick={ () =>
											setIsChangingContainer( true )
										}
									>
										{ __( 'Change', 'complyops' ) }
									</button>
								) }
							</span>
						</div>
					</li>
					<li>
						<strong>{ __( 'Container ID', 'complyops' ) }</strong>
						<span className="complyops-muted">
							{ ' ' }
							<code>{ oauth.container_id }</code>
						</span>
					</li>
					<li>
						<strong>{ __( 'Status', 'complyops' ) }</strong>
						<span>
							{ ' ' }
							<StatusBadge
								status="PASS"
								label={ __( 'Connected', 'complyops' ) }
							/>
						</span>
					</li>
				</ul>
				{ ! readOnly && (
					<p>
						<button
							type="button"
							className="button button-secondary"
							onClick={ onDisconnect }
							disabled={ disconnecting }
						>
							{ disconnecting
								? __( 'Disconnecting…', 'complyops' )
								: __( 'Disconnect', 'complyops' ) }
						</button>
					</p>
				) }
			</div>
		);
	}

	if ( isOAuthConnected ) {
		return (
			<div className="complyops-panel complyops-panel--tint">
				<h2>
					{ isChangingContainer
						? __( 'Change container', 'complyops' )
						: __( 'Connection', 'complyops' ) }
				</h2>
				{ ! isChangingContainer && (
					<ul className="complyops-list">
						<li>
							<strong>{ __( 'Account', 'complyops' ) }</strong>
							<span className="complyops-muted">
								{ ' ' }
								{ oauth.account_email ||
									__( 'Connected', 'complyops' ) }
							</span>
						</li>
					</ul>
				) }
				<GoogleTagManagerContainerPicker
					oauth={ oauth }
					readOnly={ readOnly }
					changing={ isChangingContainer }
					onCancel={
						isChangingContainer
							? () => setIsChangingContainer( false )
							: undefined
					}
					onContainerAttached={ handleContainerAttached }
				/>
				{ ! isChangingContainer && ! readOnly && (
					<p>
						<button
							type="button"
							className="button button-secondary"
							onClick={ onDisconnect }
							disabled={ disconnecting }
						>
							{ disconnecting
								? __( 'Disconnecting…', 'complyops' )
								: __( 'Disconnect', 'complyops' ) }
						</button>
					</p>
				) }
			</div>
		);
	}

	return (
		<div className="complyops-panel">
			<h2>{ __( 'Connection', 'complyops' ) }</h2>
			<p className="complyops-muted">
				{ __(
					'Sign in with Google and allow access so ComplyOps can identify your GTM container.',
					'complyops'
				) }
			</p>
			{ ! readOnly && (
				<p>
					<button
						type="button"
						className="button button-primary"
						onClick={ onConnect }
						disabled={ connecting }
					>
						{ connecting
							? __( 'Redirecting to Google…', 'complyops' )
							: __( 'Configure with Google', 'complyops' ) }
					</button>
				</p>
			) }
		</div>
	);
}

function GoogleAnalyticsDetail( {
	data,
	enforcement,
	gaSettings,
	onUpdateEnforcement,
	onUpdateGaSettings,
	readOnly,
	recommendation,
	applyingRecommendation,
	onApplyRecommendation,
	connectingOAuth,
	disconnectingOAuth,
	onConnectOAuth,
	onDisconnectOAuth,
	onPropertyAttached,
} ) {
	const gaDetail = data?.google_analytics || {};
	const integration = data?.integrations?.google_analytics || {};
	const manual = gaSettings || gaDetail.manual || {};
	const oauth = manual.oauth || {};

	return (
		<>
			<GoogleAnalyticsConnectionCard
				oauth={ oauth }
				manual={ manual }
				readOnly={ readOnly }
				connecting={ connectingOAuth }
				disconnecting={ disconnectingOAuth }
				onConnect={ onConnectOAuth }
				onDisconnect={ onDisconnectOAuth }
				onUpdateGaSettings={ onUpdateGaSettings }
				onPropertyAttached={ onPropertyAttached }
			/>
			<SiteKitStatusPanel
				siteKit={ data?.site_kit }
				service="analytics"
			/>
			<SiteKitRecommendationPanel
				recommendation={ recommendation }
				applying={ applyingRecommendation }
				onApply={ onApplyRecommendation }
				readOnly={ readOnly }
			/>

			<div className="complyops-panel">
				<h2>{ __( 'Detection', 'complyops' ) }</h2>
				{ integration.detected && (
					<p className="complyops-muted">
						{ __( 'Sources:', 'complyops' ) }{ ' ' }
						{ integration.sources?.join( ', ' ) ||
							integration.label }
					</p>
				) }
				{ gaDetail.measurement_ids?.length ? (
					<ul className="complyops-list">
						{ gaDetail.measurement_ids.map( ( id ) => (
							<li key={ id }>
								<code>{ id }</code>
							</li>
						) ) }
					</ul>
				) : (
					<p className="complyops-muted">
						{ __(
							'No GA4 measurement IDs detected on the homepage scan.',
							'complyops'
						) }
					</p>
				) }
				{ gaDetail.implementation_count > 1 && (
					<p className="complyops-notice">
						{ __(
							'Multiple GA4 IDs detected — review for duplicate implementations.',
							'complyops'
						) }
					</p>
				) }
			</div>

			<div className="complyops-panel">
				<h2>{ __( 'Configuration', 'complyops' ) }</h2>
				<p className="complyops-muted">
					{ __(
						'Requires the native ComplyOps consent manager. Consent Mode v2 defaults and script blocking apply on the public site.',
						'complyops'
					) }
				</p>
				<table className="form-table">
					<tbody>
						<tr>
							<th scope="row">
								{ __( 'Enable enforcement', 'complyops' ) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Block GA/GTM until consent and inject Consent Mode defaults',
										'complyops'
									) }
									checked={ enforcement?.enabled }
									onChange={ ( value ) =>
										onUpdateEnforcement( 'enabled', value )
									}
									disabled={ readOnly }
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								{ __( 'Consent Mode v2', 'complyops' ) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Inject gtag consent defaults in wp_head',
										'complyops'
									) }
									checked={
										enforcement?.consent_mode_enabled
									}
									onChange={ ( value ) =>
										onUpdateEnforcement(
											'consent_mode_enabled',
											value
										)
									}
									disabled={
										readOnly ||
										!! data?.site_kit?.consent_mode?.enabled
									}
								/>
								{ data?.site_kit?.consent_mode?.enabled && (
									<p className="complyops-muted">
										{ __(
											'Disabled while Site Kit Consent Mode is active. ComplyOps defers consent defaults to Site Kit.',
											'complyops'
										) }
									</p>
								) }
							</td>
						</tr>
						<tr>
							<th scope="row">
								{ __( 'Block Google Analytics', 'complyops' ) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Defer gtag.js and analytics.js until analytics consent',
										'complyops'
									) }
									checked={
										enforcement?.block_ga_before_consent
									}
									onChange={ ( value ) =>
										onUpdateEnforcement(
											'block_ga_before_consent',
											value
										)
									}
									disabled={ readOnly }
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								{ __(
									'Deny ad signals by default',
									'complyops'
								) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Keep ad_storage, ad_user_data, and ad_personalization denied until marketing consent',
										'complyops'
									) }
									checked={
										enforcement?.deny_ad_signals_by_default
									}
									onChange={ ( value ) =>
										onUpdateEnforcement(
											'deny_ad_signals_by_default',
											value
										)
									}
									disabled={ readOnly }
								/>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}

function GoogleTagManagerDetail( {
	data,
	enforcement,
	onUpdateEnforcement,
	readOnly,
	recommendation,
	applyingRecommendation,
	onApplyRecommendation,
	connectingOAuth,
	disconnectingOAuth,
	onConnectOAuth,
	onDisconnectOAuth,
	onContainerAttached,
	gtmSettings,
} ) {
	const gtmDetail = data?.google_tag_manager || {};
	const integration = data?.integrations?.google_tag_manager || {};
	const manual = gtmSettings || gtmDetail.manual || {};
	const oauth = manual.oauth || {};

	return (
		<>
			<GoogleTagManagerConnectionCard
				oauth={ oauth }
				readOnly={ readOnly }
				connecting={ connectingOAuth }
				disconnecting={ disconnectingOAuth }
				onConnect={ onConnectOAuth }
				onDisconnect={ onDisconnectOAuth }
				onContainerAttached={ onContainerAttached }
			/>
			<SiteKitStatusPanel
				siteKit={ data?.site_kit }
				service="tag_manager"
			/>
			<SiteKitRecommendationPanel
				recommendation={ recommendation }
				applying={ applyingRecommendation }
				onApply={ onApplyRecommendation }
				readOnly={ readOnly }
			/>

			<div className="complyops-panel">
				<h2>{ __( 'Detection', 'complyops' ) }</h2>
				{ integration.detected && (
					<p className="complyops-muted">
						{ __( 'Sources:', 'complyops' ) }{ ' ' }
						{ integration.sources?.join( ', ' ) ||
							integration.label }
					</p>
				) }
				{ gtmDetail.container_ids?.length ? (
					<ul className="complyops-list">
						{ gtmDetail.container_ids.map( ( id ) => (
							<li key={ id }>
								<code>{ id }</code>
							</li>
						) ) }
					</ul>
				) : (
					<p className="complyops-muted">
						{ __(
							'No GTM container IDs detected on the homepage scan.',
							'complyops'
						) }
					</p>
				) }
			</div>

			<div className="complyops-panel">
				<h2>{ __( 'Configuration', 'complyops' ) }</h2>
				<p className="complyops-muted">
					{ __(
						'GTM blocking requires global enforcement to be enabled in Google Analytics settings.',
						'complyops'
					) }
				</p>
				<table className="form-table">
					<tbody>
						<tr>
							<th scope="row">
								{ __(
									'Block Google Tag Manager',
									'complyops'
								) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Defer gtm.js until analytics consent',
										'complyops'
									) }
									checked={
										enforcement?.block_gtm_before_consent
									}
									onChange={ ( value ) =>
										onUpdateEnforcement(
											'block_gtm_before_consent',
											value
										)
									}
									disabled={ readOnly }
								/>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}

function YouTubeDetail( { data, enforcement, onUpdateEnforcement, readOnly } ) {
	const integration = data?.integrations?.youtube || {};

	return (
		<>
			<div className="complyops-panel">
				<h2>{ __( 'Detection', 'complyops' ) }</h2>
				{ integration.detected ? (
					<p className="complyops-muted">
						{ __( 'Sources:', 'complyops' ) }{ ' ' }
						{ integration.sources?.join( ', ' ) ||
							integration.label }
					</p>
				) : (
					<p className="complyops-muted">
						{ __(
							'No YouTube embeds detected on the homepage scan.',
							'complyops'
						) }
					</p>
				) }
			</div>

			<div className="complyops-panel">
				<h2>{ __( 'Configuration', 'complyops' ) }</h2>
				<table className="form-table">
					<tbody>
						<tr>
							<th scope="row">
								{ __( 'Gate YouTube embeds', 'complyops' ) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Replace YouTube iframes with placeholders until External Media consent',
										'complyops'
									) }
									checked={
										enforcement?.gate_youtube_before_consent
									}
									onChange={ ( value ) =>
										onUpdateEnforcement(
											'gate_youtube_before_consent',
											value
										)
									}
									disabled={ readOnly }
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								{ __(
									'Use youtube-nocookie.com',
									'complyops'
								) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Load embeds via youtube-nocookie.com after consent (does not replace consent requirements)',
										'complyops'
									) }
									checked={
										enforcement?.use_youtube_nocookie
									}
									onChange={ ( value ) =>
										onUpdateEnforcement(
											'use_youtube_nocookie',
											value
										)
									}
									disabled={ readOnly }
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								{ __(
									'YouTube placeholder message',
									'complyops'
								) }
							</th>
							<td>
								<textarea
									className="large-text"
									rows={ 3 }
									value={
										enforcement?.youtube_placeholder_message ||
										''
									}
									onChange={ ( event ) =>
										onUpdateEnforcement(
											'youtube_placeholder_message',
											event.target.value
										)
									}
									disabled={ readOnly }
									placeholder={ __(
										'This video is hosted by YouTube. Loading it may allow YouTube to process information about your device or activity.',
										'complyops'
									) }
								/>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}

function FormsDetail( { data } ) {
	const formInventory = data?.forms?.inventory?.forms || [];
	const formSummary = data?.forms?.inventory?.summary || {};
	const formPlugins = data?.forms?.plugins || {};

	return (
		<>
			<div className="complyops-panel">
				<h2>{ __( 'Form Inventory', 'complyops' ) }</h2>
				{ formInventory.length === 0 ? (
					<p className="complyops-muted">
						{ __(
							'No inventoried forms yet. Install a supported form plugin and run discovery.',
							'complyops'
						) }
					</p>
				) : (
					<>
						<p className="complyops-muted">
							{ __( 'Summary:', 'complyops' ) }{ ' ' }
							{ formSummary.total_forms || 0 }{ ' ' }
							{ __( 'forms', 'complyops' ) },{ ' ' }
							{ formSummary.forms_with_email || 0 }{ ' ' }
							{ __( 'with email', 'complyops' ) },{ ' ' }
							{ formSummary.forms_with_sensitive_fields || 0 }{ ' ' }
							{ __(
								'with sensitive fields flagged',
								'complyops'
							) }
						</p>
						<ul className="complyops-list">
							{ formInventory.map( ( form ) => (
								<li key={ `${ form.plugin }-${ form.id }` }>
									<strong>{ form.title }</strong>
									<span className="complyops-muted">
										{ ' ' }
										— { form.plugin } (
										{ form.summary?.field_count || 0 }{ ' ' }
										{ __( 'fields', 'complyops' ) })
									</span>
									{ form.summary?.has_sensitive_fields && (
										<span className="complyops-notice">
											{ ' ' }
											—{ ' ' }
											{ __(
												'sensitive fields flagged',
												'complyops'
											) }
										</span>
									) }
								</li>
							) ) }
						</ul>
					</>
				) }
			</div>

			<div className="complyops-panel">
				<h2>{ __( 'Form Plugins', 'complyops' ) }</h2>
				<ul className="complyops-list">
					{ Object.entries( formPlugins ).map( ( [ id, item ] ) => (
						<li key={ id }>
							<strong>{ item.label }</strong>
							{ item.detected
								? ` — ${ item.form_count } ${ __(
										'forms',
										'complyops'
								  ) }`
								: ` — ${ __( 'not detected', 'complyops' ) }` }
						</li>
					) ) }
				</ul>
			</div>
		</>
	);
}

function OtherServicesDetail( { data } ) {
	const integrations = Object.entries( data?.integrations || {} ).filter(
		( [ id, item ] ) => item.detected && ! PRIMARY_INTEGRATION_IDS.has( id )
	);

	return (
		<div className="complyops-panel">
			<h2>{ __( 'Detected Services', 'complyops' ) }</h2>
			{ integrations.length === 0 ? (
				<p className="complyops-muted">
					{ __(
						'No additional integrations detected yet.',
						'complyops'
					) }
				</p>
			) : (
				<ul className="complyops-list">
					{ integrations.map( ( [ id, item ] ) => (
						<li key={ id }>
							<strong>{ item.label }</strong>
							<span className="complyops-muted">
								{ ' ' }
								— { item.sources?.join( ', ' ) || id }
							</span>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

function ThirdPartyDetail( { data } ) {
	const reviewItems = data?.third_party?.review_items || [];

	return (
		<div className="complyops-panel">
			<h2>{ __( 'Third-Party Review', 'complyops' ) }</h2>
			{ reviewItems.length === 0 ? (
				<p className="complyops-muted">
					{ __(
						'No unknown third-party hosts flagged on the homepage scan.',
						'complyops'
					) }
				</p>
			) : (
				<ul className="complyops-list">
					{ reviewItems.map( ( item ) => (
						<li key={ item.host }>
							<strong>{ item.host }</strong>
							<span className="complyops-muted">
								{ ' ' }
								— { item.classification } / { item.status }
							</span>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

function ConsentProvidersDetail( { data } ) {
	const providers = data?.consent?.providers || {};

	return (
		<div className="complyops-panel">
			<h2>{ __( 'Consent Providers', 'complyops' ) }</h2>
			<ul className="complyops-list">
				{ Object.entries( providers ).map( ( [ id, item ] ) => (
					<li key={ id }>
						<strong>{ item.label }</strong>
						{ item.detected
							? ` — ${ __( 'detected', 'complyops' ) }`
							: '' }
					</li>
				) ) }
			</ul>
		</div>
	);
}

function BrowserVerificationDetail( {
	data,
	browserVerificationSettings,
	onUpdateBrowserVerificationSettings,
	readOnly,
} ) {
	const verification = data?.browser_verification || {};
	const [ testing, setTesting ] = useState( false );
	const [ requesting, setRequesting ] = useState( false );
	const [ testMessage, setTestMessage ] = useState( null );
	const notify = useNotify();
	const tokenInput = ( browserVerificationSettings?.token || '' ).trim();
	const tokenPreview = tokenInput
		? obfuscateTokenPreview( tokenInput )
		: browserVerificationSettings?.token_preview || '';

	const runTest = async () => {
		if ( readOnly ) {
			return;
		}

		setTesting( true );
		setTestMessage( null );

		try {
			const response = await apiFetch( {
				path: `/${ NS }/browser-verification/test`,
				method: 'POST',
				data: {
					service_url: browserVerificationSettings?.service_url || '',
					...( ( browserVerificationSettings?.token || '' ).trim()
						? { token: browserVerificationSettings.token }
						: {} ),
				},
			} );
			setTestMessage(
				response?.message || __( 'Connected.', 'complyops' )
			);
			notify.success(
				response?.message ||
					__( 'Connected to hosted browser service.', 'complyops' )
			);
		} catch ( err ) {
			const message =
				err?.message || __( 'Connection test failed.', 'complyops' );
			setTestMessage( message );
			notify.error( message );
		} finally {
			setTesting( false );
		}
	};

	const startRequest = async () => {
		if ( readOnly ) {
			return;
		}

		setRequesting( true );
		setTestMessage( null );

		try {
			const returnUrl = window.location.href.split( '#' )[ 0 ];
			const response = await apiFetch( {
				path: `/${ NS }/browser-verification/request`,
				method: 'POST',
				data: { return_url: returnUrl },
			} );

			if ( response?.form_url ) {
				window.open(
					response.form_url,
					'_blank',
					'noopener,noreferrer'
				);
			}

			setTestMessage(
				__(
					'Complete the ncdLabs form in the new browser tab. You will return here automatically when the site token is issued.',
					'complyops'
				)
			);
			notify.success(
				__(
					'Browser verification request started. Complete the form on ncdLabs.com.',
					'complyops'
				)
			);
		} catch ( err ) {
			const message =
				err?.message ||
				__(
					'Could not start hosted browser verification request.',
					'complyops'
				);
			setTestMessage( message );
			notify.error( message );
		} finally {
			setRequesting( false );
		}
	};

	return (
		<>
			<div className="complyops-panel">
				<h2>{ __( 'Status', 'complyops' ) }</h2>
				<ul className="complyops-list">
					<li>
						<strong>{ __( 'Last scan', 'complyops' ) }</strong>
						<span className="complyops-muted">
							{ ' ' }
							—{ ' ' }
							{ verification.available
								? __( 'Available', 'complyops' )
								: __( 'Unavailable', 'complyops' ) }
						</span>
					</li>
					{ verification.mode && (
						<li>
							<strong>{ __( 'Mode', 'complyops' ) }</strong>
							<span className="complyops-muted">
								{ ' ' }
								—{ ' ' }
								{ verification.mode === 'remote'
									? __( 'Hosted service', 'complyops' )
									: __( 'Local Chromium', 'complyops' ) }
							</span>
						</li>
					) }
					{ browserVerificationSettings?.local_available && (
						<li>
							<strong>
								{ __( 'Local Chromium', 'complyops' ) }
							</strong>
							<span className="complyops-muted">
								{ ' ' }
								—{ ' ' }
								{ __( 'Available on this host', 'complyops' ) }
							</span>
						</li>
					) }
					{ verification.error && ! verification.available && (
						<li>
							<strong>{ __( 'Error', 'complyops' ) }</strong>
							<span className="complyops-muted">
								{ ' ' }
								— { verification.error }
							</span>
						</li>
					) }
					{ verification.remote_error && (
						<li>
							<strong>
								{ __( 'Remote fallback', 'complyops' ) }
							</strong>
							<span className="complyops-muted">
								{ ' ' }
								— { verification.remote_error }
							</span>
						</li>
					) }
				</ul>
			</div>

			<div className="complyops-panel">
				<h2>{ __( 'Configuration', 'complyops' ) }</h2>
				<p className="complyops-muted">
					{ __(
						'Use a hosted complyops-browser service when this WordPress host cannot run Chromium (typical on shared hosting). The service URL must be reachable from this server.',
						'complyops'
					) }
				</p>
				<p className="complyops-muted">
					{ __(
						'Need a site token? Request one on ncdLabs.com with your pack unlock key and contact details. Pack import still provisions automatically when activation succeeds.',
						'complyops'
					) }
				</p>
				<table className="form-table">
					<tbody>
						<tr>
							<th scope="row">
								{ __( 'Use hosted service', 'complyops' ) }
							</th>
							<td>
								<ConfigCheckbox
									label={ __(
										'Run browser verification through the remote service',
										'complyops'
									) }
									checked={
										browserVerificationSettings?.enabled
									}
									onChange={ ( value ) =>
										onUpdateBrowserVerificationSettings(
											'enabled',
											value
										)
									}
									disabled={ readOnly }
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								{ __( 'Service URL', 'complyops' ) }
							</th>
							<td>
								<input
									type="url"
									className="regular-text"
									value={
										browserVerificationSettings?.service_url ||
										''
									}
									onChange={ ( event ) =>
										onUpdateBrowserVerificationSettings(
											'service_url',
											event.target.value
										)
									}
									disabled={ readOnly }
									placeholder={
										browserVerificationSettings?.service_url ||
										browserVerificationSettings?.default_service_url ||
										'https://browser-verify.ncdlabs.com'
									}
								/>
								<p className="description">
									{ __(
										'Base URL without /verify. Defaults to the hosted ncdLabs service when empty.',
										'complyops'
									) }
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								{ __( 'Service token', 'complyops' ) }
							</th>
							<td>
								<input
									type="password"
									className="regular-text"
									value={
										browserVerificationSettings?.token || ''
									}
									onChange={ ( event ) =>
										onUpdateBrowserVerificationSettings(
											'token',
											event.target.value
										)
									}
									disabled={ readOnly }
									placeholder={
										tokenPreview
											? __(
													'Enter new token to replace',
													'complyops'
											  )
											: ''
									}
									autoComplete="off"
								/>
								{ tokenPreview && (
									<p className="description">
										<code>{ tokenPreview }</code>
									</p>
								) }
							</td>
						</tr>
					</tbody>
				</table>
				{ ! readOnly && (
					<p>
						<button
							type="button"
							className="button button-secondary"
							onClick={ startRequest }
							disabled={ requesting }
						>
							{ requesting
								? __( 'Starting request…', 'complyops' )
								: __(
										'Request site token on ncdLabs.com',
										'complyops'
								  ) }
						</button>
						<button
							type="button"
							className="button button-secondary"
							onClick={ runTest }
							disabled={
								testing ||
								! browserVerificationSettings?.service_url ||
								( ! (
									browserVerificationSettings?.token || ''
								).trim() &&
									! browserVerificationSettings?.token_set )
							}
							style={ { marginLeft: '8px' } }
						>
							{ testing
								? __( 'Testing…', 'complyops' )
								: __( 'Test connection', 'complyops' ) }
						</button>
					</p>
				) }
				{ testMessage && (
					<p className="complyops-muted">{ testMessage }</p>
				) }
			</div>
		</>
	);
}

function PackDetailView( {
	pack,
	data,
	enforcement,
	gaSettings,
	gtmSettings,
	onUpdateEnforcement,
	onUpdateGaSettings,
	readOnly,
	onBack,
	recommendation,
	applyingRecommendation,
	onApplyRecommendation,
	connectingGaOAuth,
	disconnectingGaOAuth,
	onConnectGaOAuth,
	onDisconnectGaOAuth,
	onPropertyAttached,
	connectingGtmOAuth,
	disconnectingGtmOAuth,
	onConnectGtmOAuth,
	onDisconnectGtmOAuth,
	onContainerAttached,
	browserVerificationSettings,
	onUpdateBrowserVerificationSettings,
} ) {
	const detailProps = {
		data,
		enforcement,
		gaSettings,
		gtmSettings,
		onUpdateEnforcement,
		onUpdateGaSettings,
		readOnly,
		recommendation,
		applyingRecommendation,
		onApplyRecommendation,
		browserVerificationSettings,
		onUpdateBrowserVerificationSettings,
	};

	let content = null;
	switch ( pack?.id ) {
		case 'google-analytics':
			content = (
				<GoogleAnalyticsDetail
					{ ...detailProps }
					connectingOAuth={ connectingGaOAuth }
					disconnectingOAuth={ disconnectingGaOAuth }
					onConnectOAuth={ onConnectGaOAuth }
					onDisconnectOAuth={ onDisconnectGaOAuth }
					onPropertyAttached={ onPropertyAttached }
				/>
			);
			break;
		case 'google-tag-manager':
			content = (
				<GoogleTagManagerDetail
					{ ...detailProps }
					connectingOAuth={ connectingGtmOAuth }
					disconnectingOAuth={ disconnectingGtmOAuth }
					onConnectOAuth={ onConnectGtmOAuth }
					onDisconnectOAuth={ onDisconnectGtmOAuth }
					onContainerAttached={ onContainerAttached }
				/>
			);
			break;
		case 'youtube':
			content = <YouTubeDetail { ...detailProps } />;
			break;
		case 'browser-verification':
			content = <BrowserVerificationDetail { ...detailProps } />;
			break;
		case 'forms':
			content = <FormsDetail data={ data } />;
			break;
		case 'other-services':
			content = <OtherServicesDetail data={ data } />;
			break;
		case 'third-party':
			content = <ThirdPartyDetail data={ data } />;
			break;
		case 'consent-providers':
			content = <ConsentProvidersDetail data={ data } />;
			break;
		default:
			content = null;
	}

	return (
		<div className="complyops-detail-screen">
			<DetailBackLink
				onClick={ onBack }
				label={ __( 'Back to integration packs', 'complyops' ) }
			/>

			<PageHeader
				eyebrow={ __( 'Manage', 'complyops' ) }
				title={
					pack?.label || __( 'Integration details', 'complyops' )
				}
				description={ pack?.subtitle || '' }
			/>

			{ content }
		</div>
	);
}

export default function IntegrationsPage() {
	const [ data, setData ] = useState( null );
	const [ enforcement, setEnforcement ] = useState( null );
	const [ gaSettings, setGaSettings ] = useState( null );
	const [ gtmSettings, setGtmSettings ] = useState( null );
	const [ browserVerificationSettings, setBrowserVerificationSettings ] =
		useState( null );
	const [ baseline, setBaseline ] = useState( null );
	const [ gaBaseline, setGaBaseline ] = useState( null );
	const [ bvBaseline, setBvBaseline ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ packId, setPackId ] = useState(
		() => getQueryParam( 'pack' ) || ''
	);
	const [ applyingRecommendation, setApplyingRecommendation ] =
		useState( false );
	const [ connectingGaOAuth, setConnectingGaOAuth ] = useState( false );
	const [ disconnectingGaOAuth, setDisconnectingGaOAuth ] = useState( false );
	const [ connectingGtmOAuth, setConnectingGtmOAuth ] = useState( false );
	const [ disconnectingGtmOAuth, setDisconnectingGtmOAuth ] =
		useState( false );
	const notify = useNotify();

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const [ integrations, settings ] = await Promise.all( [
				getIntegrations(),
				apiFetch( { path: `/${ NS }/settings` } ),
			] );
			setData( integrations );
			const loaded = settings.enforcement || {};
			const loadedGa = settings.google_analytics || {};
			const loadedGtm = integrations.google_tag_manager?.manual || {};
			const loadedBv = settings.browser_verification || {};
			setEnforcement( loaded );
			setGaSettings( loadedGa );
			setGtmSettings( loadedGtm );
			setBrowserVerificationSettings( loadedBv );
			setBaseline( loaded );
			setGaBaseline( loadedGa );
			setBvBaseline( loadedBv );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to load integrations.', 'complyops' )
			);
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		if ( ! canManage || loading ) {
			return;
		}

		if ( getQueryParam( 'pack' ) !== 'browser-verification' ) {
			return;
		}

		const requestId = getQueryParam( 'complyops_bv_request_id' );
		const exchangeCode = getQueryParam( 'complyops_bv_exchange_code' );

		if ( ! requestId || ! exchangeCode ) {
			return;
		}

		let cancelled = false;

		( async () => {
			try {
				const response = await apiFetch( {
					path: `/${ NS }/browser-verification/claim`,
					method: 'POST',
					data: {
						request_id: requestId,
						exchange_code: exchangeCode,
					},
				} );

				if ( cancelled ) {
					return;
				}

				const savedBv = response.browser_verification || null;
				if ( savedBv ) {
					setBrowserVerificationSettings( savedBv );
					setBvBaseline( savedBv );
				}

				notify.success(
					response?.message ||
						__(
							'Hosted browser verification credentials saved.',
							'complyops'
						)
				);
			} catch ( err ) {
				if ( ! cancelled ) {
					notify.error(
						err?.message ||
							__(
								'Could not claim hosted browser verification credentials.',
								'complyops'
							)
					);
				}
			} finally {
				setQueryParam( 'complyops_bv_request_id', null );
				setQueryParam( 'complyops_bv_exchange_code', null );
			}
		} )();

		return () => {
			cancelled = true;
		};
	}, [ loading, notify ] );

	useEffect( () => {
		if ( ! canManage ) {
			return;
		}

		const activePack = getQueryParam( 'pack' ) || 'google-analytics';
		const isGtm = activePack === 'google-tag-manager';
		const oauthStatus =
			getQueryParam( 'complyops_google_oauth' ) ||
			getQueryParam( 'complyops_ga_oauth' );
		const oauthMessage =
			getQueryParam( 'complyops_google_message' ) ||
			getQueryParam( 'complyops_ga_message' );
		const oauthCode =
			getQueryParam( 'google_oauth_code' ) ||
			getQueryParam( 'ga_oauth_code' );
		const oauthState =
			getQueryParam( 'google_oauth_state' ) ||
			getQueryParam( 'ga_oauth_state' );

		const clearOAuthParams = () => {
			setQueryParam( 'complyops_google_oauth', null );
			setQueryParam( 'complyops_google_message', null );
			setQueryParam( 'google_oauth_code', null );
			setQueryParam( 'google_oauth_state', null );
			setQueryParam( 'complyops_ga_oauth', null );
			setQueryParam( 'complyops_ga_message', null );
			setQueryParam( 'ga_oauth_code', null );
			setQueryParam( 'ga_oauth_state', null );
		};

		if ( oauthStatus === 'success' ) {
			clearOAuthParams();
			notify.success(
				oauthMessage
					? decodeURIComponent( oauthMessage )
					: isGtm
					? __( 'Google Tag Manager is connected.', 'complyops' )
					: __( 'Google Analytics is connected.', 'complyops' )
			);
			load();
			return;
		}

		if ( oauthStatus === 'error' ) {
			clearOAuthParams();
			notify.error(
				oauthMessage
					? decodeURIComponent( oauthMessage )
					: __( 'Google sign-in failed.', 'complyops' )
			);
			return;
		}

		if ( ! oauthCode || ! oauthState ) {
			return;
		}

		const completionKey = `complyops_google_oauth:${ oauthCode }:${ oauthState }`;
		if ( window.sessionStorage?.getItem( completionKey ) ) {
			clearOAuthParams();
			return;
		}
		window.sessionStorage?.setItem( completionKey, 'pending' );

		let cancelled = false;

		( async () => {
			setConnectingGaOAuth( true );
			setConnectingGtmOAuth( true );
			setError( null );

			try {
				const response = isGtm
					? await completeGoogleTagManagerOAuth(
							oauthCode,
							oauthState
					  )
					: await completeGoogleAnalyticsOAuth(
							oauthCode,
							oauthState
					  );
				if ( cancelled ) {
					return;
				}

				if ( isGtm ) {
					const savedGtm = response.google_tag_manager || gtmSettings;
					setGtmSettings( savedGtm );
					setData( ( current ) => ( {
						...current,
						google_tag_manager: {
							...( current?.google_tag_manager || {} ),
							manual: savedGtm,
						},
					} ) );
					if ( savedGtm?.oauth?.needs_container_selection ) {
						notify.success(
							__(
								'Google account connected. Choose a GTM container to finish setup.',
								'complyops'
							)
						);
					} else {
						notify.success(
							__(
								'Google Tag Manager is connected.',
								'complyops'
							)
						);
					}
				} else {
					const savedGa = response.google_analytics || gaSettings;
					setGaSettings( savedGa );
					setGaBaseline( savedGa );
					setData( ( current ) => ( {
						...current,
						google_analytics: {
							...( current?.google_analytics || {} ),
							manual: savedGa,
							measurement_ids: mergeMeasurementIds(
								current?.google_analytics?.measurement_ids,
								savedGa.measurement_id
							),
						},
					} ) );
					if ( savedGa?.oauth?.needs_property_selection ) {
						notify.success(
							__(
								'Google account connected. Choose a GA4 property to finish setup.',
								'complyops'
							)
						);
					} else {
						notify.success(
							__( 'Google Analytics is connected.', 'complyops' )
						);
					}
				}
				window.sessionStorage?.setItem( completionKey, 'done' );
				await load();
			} catch ( err ) {
				if ( ! cancelled ) {
					window.sessionStorage?.removeItem( completionKey );
					notify.error(
						err?.message ||
							__( 'Google sign-in failed.', 'complyops' )
					);
				}
			} finally {
				clearOAuthParams();
				if ( ! cancelled ) {
					setConnectingGaOAuth( false );
					setConnectingGtmOAuth( false );
				}
			}
		} )();

		return () => {
			cancelled = true;
		};
	}, [ gaSettings, gtmSettings, load, notify ] );

	const packs = useMemo(
		() =>
			buildPackSummaries(
				data,
				enforcement,
				browserVerificationSettings
			),
		[ data, enforcement, browserVerificationSettings ]
	);

	const activePack = packs.find( ( item ) => item.id === packId ) || null;
	const gaOAuthConnected = !! gaSettings?.oauth?.connected;
	const dirty =
		isFormDirty( baseline, enforcement ) ||
		( ! gaOAuthConnected && isFormDirty( gaBaseline, gaSettings ) ) ||
		isFormDirty( bvBaseline, browserVerificationSettings );
	const readOnly = ! canManage;

	const openPack = ( id ) => {
		setPackId( id );
		setQueryParam( 'pack', id );
	};

	const closePack = () => {
		setPackId( '' );
		setQueryParam( 'pack', null );
	};

	const updateEnforcement = ( field, value ) => {
		setEnforcement( ( current ) => ( { ...current, [ field ]: value } ) );
	};

	const updateGaSettings = ( field, value ) => {
		setGaSettings( ( current ) => {
			if ( current?.oauth?.connected ) {
				return current;
			}

			return { ...current, [ field ]: value };
		} );
	};

	const updateBrowserVerificationSettings = ( field, value ) => {
		setBrowserVerificationSettings( ( current ) => ( {
			...current,
			[ field ]: value,
		} ) );
	};

	const buildBvSavePayload = () => {
		if ( ! browserVerificationSettings ) {
			return null;
		}

		const payload = { ...browserVerificationSettings };
		delete payload.configured;
		delete payload.token_set;
		delete payload.token_preview;
		delete payload.local_available;
		delete payload.default_service_url;

		if ( '' === ( payload.token || '' ).trim() ) {
			delete payload.token;
		}

		return payload;
	};

	const buildGaSavePayload = () => {
		if ( ! gaSettings || gaSettings.oauth?.connected ) {
			return null;
		}

		const payload = { ...gaSettings };
		delete payload.configured;
		delete payload.oauth;
		delete payload.oauth_client_secret_set;

		if ( '' === ( payload.oauth_client_secret || '' ).trim() ) {
			delete payload.oauth_client_secret;
		}

		return payload;
	};

	const saveEnforcement = async () => {
		if ( ! canManage ) {
			return;
		}

		setSaving( true );
		setError( null );

		try {
			const payload = { enforcement };
			const gaPayload = buildGaSavePayload();
			const bvPayload = buildBvSavePayload();
			if ( packId === 'google-analytics' && gaPayload ) {
				payload.google_analytics = gaPayload;
			}
			if ( packId === 'browser-verification' && bvPayload ) {
				payload.browser_verification = bvPayload;
			}

			const response = await apiFetch( {
				path: `/${ NS }/settings`,
				method: 'PUT',
				data: payload,
			} );
			const saved = response.enforcement || enforcement;
			const savedGa = response.google_analytics || gaSettings;
			const savedBv =
				response.browser_verification || browserVerificationSettings;
			setEnforcement( saved );
			setGaSettings( savedGa );
			setBrowserVerificationSettings( savedBv );
			setBaseline( saved );
			setGaBaseline( savedGa );
			setBvBaseline( savedBv );
			if ( savedGa ) {
				setData( ( current ) => ( {
					...current,
					google_analytics: {
						...( current?.google_analytics || {} ),
						manual: savedGa,
						measurement_ids: mergeMeasurementIds(
							current?.google_analytics?.measurement_ids,
							savedGa.measurement_id
						),
					},
				} ) );
			}
			notify.success( __( 'Settings saved.', 'complyops' ) );
		} catch ( err ) {
			notify.error(
				err?.message ||
					__( 'Failed to save enforcement settings.', 'complyops' )
			);
		} finally {
			setSaving( false );
		}
	};

	const cancel = () => {
		setEnforcement( baseline );
		setGaSettings( gaBaseline );
		setBrowserVerificationSettings( bvBaseline );
		setError( null );
	};

	const connectGaOAuth = async () => {
		if ( ! canManage ) {
			return;
		}

		setConnectingGaOAuth( true );
		setError( null );

		try {
			const response = await startGoogleAnalyticsOAuth();
			if ( response?.authorization_url ) {
				window.location.assign( response.authorization_url );
				return;
			}

			throw new Error(
				__( 'Google sign-in could not be started.', 'complyops' )
			);
		} catch ( err ) {
			setConnectingGaOAuth( false );
			notify.error(
				err?.message ||
					__( 'Google sign-in could not be started.', 'complyops' )
			);
		}
	};

	const disconnectGaOAuth = async () => {
		if ( ! canManage ) {
			return;
		}

		setDisconnectingGaOAuth( true );
		setError( null );

		try {
			const response = await disconnectGoogleAnalyticsOAuth();
			const savedGa = response.google_analytics || gaSettings;
			const savedGtm = response.google_tag_manager || gtmSettings;
			setGaSettings( savedGa );
			setGaBaseline( savedGa );
			setGtmSettings( savedGtm );
			setData( ( current ) => ( {
				...current,
				google_analytics: {
					...( current?.google_analytics || {} ),
					manual: savedGa,
				},
				google_tag_manager: {
					...( current?.google_tag_manager || {} ),
					manual: savedGtm,
				},
			} ) );
			notify.success( __( 'Google account disconnected.', 'complyops' ) );
		} catch ( err ) {
			notify.error(
				err?.message ||
					__( 'Failed to disconnect Google account.', 'complyops' )
			);
		} finally {
			setDisconnectingGaOAuth( false );
		}
	};

	const connectGtmOAuth = async () => {
		if ( ! canManage ) {
			return;
		}

		setConnectingGtmOAuth( true );
		setError( null );

		try {
			const response = await startGoogleTagManagerOAuth();
			if ( response?.authorization_url ) {
				window.location.assign( response.authorization_url );
				return;
			}

			throw new Error(
				__( 'Google sign-in could not be started.', 'complyops' )
			);
		} catch ( err ) {
			setConnectingGtmOAuth( false );
			notify.error(
				err?.message ||
					__( 'Google sign-in could not be started.', 'complyops' )
			);
		}
	};

	const disconnectGtmOAuth = async () => {
		if ( ! canManage ) {
			return;
		}

		setDisconnectingGtmOAuth( true );
		setError( null );

		try {
			const response = await disconnectGoogleTagManagerOAuth();
			const savedGtm = response.google_tag_manager || gtmSettings;
			const savedGa = response.google_analytics || gaSettings;
			setGtmSettings( savedGtm );
			setGaSettings( savedGa );
			setGaBaseline( savedGa );
			setData( ( current ) => ( {
				...current,
				google_tag_manager: {
					...( current?.google_tag_manager || {} ),
					manual: savedGtm,
				},
				google_analytics: {
					...( current?.google_analytics || {} ),
					manual: savedGa,
				},
			} ) );
			notify.success( __( 'Google account disconnected.', 'complyops' ) );
		} catch ( err ) {
			notify.error(
				err?.message ||
					__( 'Failed to disconnect Google account.', 'complyops' )
			);
		} finally {
			setDisconnectingGtmOAuth( false );
		}
	};

	const attachGtmContainer = ( response ) => {
		const savedGtm = response.google_tag_manager || gtmSettings;
		setGtmSettings( savedGtm );
		setData( ( current ) => ( {
			...current,
			google_tag_manager: {
				...( current?.google_tag_manager || {} ),
				manual: savedGtm,
			},
		} ) );
		notify.success(
			__( 'Google Tag Manager container attached.', 'complyops' )
		);
	};

	const attachGaProperty = ( response ) => {
		const savedGa = response.google_analytics || gaSettings;
		setGaSettings( savedGa );
		setGaBaseline( savedGa );
		setData( ( current ) => ( {
			...current,
			google_analytics: {
				...( current?.google_analytics || {} ),
				manual: savedGa,
				measurement_ids: mergeMeasurementIds(
					current?.google_analytics?.measurement_ids,
					savedGa.measurement_id
				),
			},
		} ) );
		notify.success(
			__( 'Google Analytics property attached.', 'complyops' )
		);
	};

	const applyRecommendation = async () => {
		if ( ! canManage ) {
			return;
		}

		setApplyingRecommendation( true );
		setError( null );

		try {
			const response = await applySiteKitRecommended();
			const saved = response.enforcement || enforcement;
			setEnforcement( saved );
			setBaseline( saved );
			if ( response.site_kit ) {
				setData( ( current ) => ( {
					...current,
					site_kit: response.site_kit,
				} ) );
			}
			notify.success(
				__(
					'Applied Site Kit enforcement recommendations.',
					'complyops'
				)
			);
		} catch ( err ) {
			notify.error(
				err?.message ||
					__(
						'Failed to apply Site Kit recommendations.',
						'complyops'
					)
			);
		} finally {
			setApplyingRecommendation( false );
		}
	};

	const recommendation = data?.site_kit?.recommendation || null;

	const showSaveFooter = !! packId && activePack?.hasConfig && canManage;

	const pageBody = packId ? (
		<PackDetailView
			pack={ activePack }
			data={ data }
			enforcement={ enforcement }
			gaSettings={ gaSettings }
			gtmSettings={ gtmSettings }
			onUpdateEnforcement={ updateEnforcement }
			onUpdateGaSettings={ updateGaSettings }
			readOnly={ readOnly }
			onBack={ closePack }
			recommendation={ recommendation }
			applyingRecommendation={ applyingRecommendation }
			onApplyRecommendation={ applyRecommendation }
			connectingGaOAuth={ connectingGaOAuth }
			disconnectingGaOAuth={ disconnectingGaOAuth }
			onConnectGaOAuth={ connectGaOAuth }
			onDisconnectGaOAuth={ disconnectGaOAuth }
			onPropertyAttached={ attachGaProperty }
			connectingGtmOAuth={ connectingGtmOAuth }
			disconnectingGtmOAuth={ disconnectingGtmOAuth }
			onConnectGtmOAuth={ connectGtmOAuth }
			onDisconnectGtmOAuth={ disconnectGtmOAuth }
			onContainerAttached={ attachGtmContainer }
			browserVerificationSettings={ browserVerificationSettings }
			onUpdateBrowserVerificationSettings={
				updateBrowserVerificationSettings
			}
		/>
	) : (
		<PackListView
			packs={ packs }
			loading={ loading }
			error={ error }
			onRetry={ load }
			onOpenPack={ openPack }
		/>
	);

	return (
		<FormPage
			footer={
				showSaveFooter ? (
					<SaveFooter
						onSave={ saveEnforcement }
						onCancel={ cancel }
						saving={ saving }
						dirty={ dirty }
						saveLabel={ __( 'Save', 'complyops' ) }
					/>
				) : null
			}
		>
			<div className="complyops-integrations-page">{ pageBody }</div>
		</FormPage>
	);
}
