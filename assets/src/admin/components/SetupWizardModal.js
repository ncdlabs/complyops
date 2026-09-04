import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getLatestAudit, getSettings, runAudit, updateSettings } from '../api';
import BannerDesigner from './BannerDesigner';
import { Dialog } from './Dialog';
import { Alert, StatusBadge } from './ui';

const STORE_URL =
	window.complyopsAdmin?.storeUrl ||
	'https://ncdlabs.com/products/complyops/store/';
const BRAND_MARK_URL = window.complyopsAdmin?.brandMarkUrl || '';
const TOTAL_STEPS = 6;
const SETUP_SNOOZE_KEY = 'complyops_setup_prompt_snoozed';

function isSetupSnoozed() {
	try {
		return sessionStorage.getItem( SETUP_SNOOZE_KEY ) === '1';
	} catch {
		return false;
	}
}

function snoozeSetup() {
	try {
		sessionStorage.setItem( SETUP_SNOOZE_KEY, '1' );
	} catch {
		// Ignore storage failures.
	}
}

function clearSetupSnooze() {
	try {
		sessionStorage.removeItem( SETUP_SNOOZE_KEY );
	} catch {
		// Ignore storage failures.
	}
}

function ComplyOpsLogo( { className = '' } ) {
	return (
		<span className={ `complyops-logo ${ className }`.trim() }>
			{ BRAND_MARK_URL ? (
				<img src={ BRAND_MARK_URL } alt="" aria-hidden="true" />
			) : null }
			<span className="complyops-logo__wordmark" aria-label="ComplyOps">
				Comply<span>Ops</span>
			</span>
		</span>
	);
}

function SetupWizardAuditGraphic() {
	const title = __( 'Technical compliance audit', 'complyops' );

	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 120 96"
			role="img"
			aria-labelledby="complyops-setup-audit-graphic-title"
		>
			<title id="complyops-setup-audit-graphic-title">{ title }</title>
			<path
				fill="#21a4f3"
				opacity="0.18"
				d="M60 8 98 22v24c0 22-16 38-38 44-22-6-38-22-38-44V22L60 8Z"
			/>
			<path
				fill="#0878f9"
				d="M60 12 92 24v20c0 18-14 32-32 37-18-5-32-19-32-37V24L60 12Z"
			/>
			<path
				fill="none"
				stroke="#fff"
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="4"
				d="m42 44 10 10 26-22"
			/>
			<circle
				cx="82"
				cy="58"
				r="15"
				fill="#fff"
				stroke="#168a6b"
				strokeWidth="3"
			/>
			<path
				fill="none"
				stroke="#168a6b"
				strokeLinecap="round"
				strokeWidth="4"
				d="M93 69 106 82"
			/>
			<circle cx="77" cy="53" r="2.5" fill="#168a6b" />
			<circle cx="82" cy="58" r="2.5" fill="#168a6b" />
			<circle cx="87" cy="63" r="2.5" fill="#168a6b" />
		</svg>
	);
}

function SetupWizardMonitoringGraphic() {
	const title = __( 'Scheduled monitoring', 'complyops' );

	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 120 96"
			role="img"
			aria-labelledby="complyops-setup-monitoring-graphic-title"
		>
			<title id="complyops-setup-monitoring-graphic-title">
				{ title }
			</title>
			<rect
				x="18"
				y="16"
				width="56"
				height="52"
				rx="8"
				fill="#fff"
				stroke="#0878f9"
				strokeWidth="2.5"
			/>
			<rect x="18" y="16" width="56" height="14" rx="8" fill="#0878f9" />
			<circle cx="28" cy="23" r="2" fill="#fff" />
			<circle cx="36" cy="23" r="2" fill="#fff" />
			<circle cx="44" cy="23" r="2" fill="#fff" />
			<path
				stroke="#168a6b"
				strokeWidth="2.5"
				strokeLinecap="round"
				d="M28 42h36M28 50h28M28 58h20"
			/>
			<circle
				cx="84"
				cy="54"
				r="22"
				fill="#fff"
				stroke="#168a6b"
				strokeWidth="3"
			/>
			<path
				fill="none"
				stroke="#168a6b"
				strokeLinecap="round"
				strokeWidth="3"
				d="M84 42v12l8 6"
			/>
			<path
				fill="none"
				stroke="#21a4f3"
				strokeLinecap="round"
				strokeWidth="2.5"
				d="M92 24a18 18 0 0 1 8 24"
			/>
			<path
				fill="none"
				stroke="#21a4f3"
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="2.5"
				d="m98 24 2-6 2 6"
			/>
		</svg>
	);
}

function SetupWizardPublicStatusGraphic() {
	const title = __( 'Verified compliance status', 'complyops' );

	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 120 96"
			role="img"
			aria-labelledby="complyops-setup-public-graphic-title"
		>
			<title id="complyops-setup-public-graphic-title">{ title }</title>
			<circle cx="60" cy="48" r="40" fill="#e8f6f0" />
			<rect
				x="24"
				y="12"
				width="64"
				height="72"
				rx="8"
				fill="#fff"
				stroke="#168a6b"
				strokeWidth="2.5"
			/>
			<path
				fill="#168a6b"
				d="M34 12h44a10 10 0 0 1 10 10v4H24v-4a10 10 0 0 1 10-10Z"
			/>
			<circle cx="34" cy="19" r="2" fill="#fff" />
			<circle cx="42" cy="19" r="2" fill="#fff" />
			<circle cx="50" cy="19" r="2" fill="#fff" />
			<path
				stroke="#8aa79e"
				strokeWidth="2.5"
				strokeLinecap="round"
				d="M34 38h38M34 46h30M34 54h24M34 62h20"
			/>
			<circle
				cx="86"
				cy="64"
				r="20"
				fill="#168a6b"
				stroke="#fff"
				strokeWidth="4"
			/>
			<path
				fill="none"
				stroke="#fff"
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="4"
				d="m76 64 7 7 13-15"
			/>
		</svg>
	);
}

function SetupWizardStepGraphic( { align = 'left', children } ) {
	const className = [
		'complyops-setup-wizard__step-graphic',
		align === 'right' && 'complyops-setup-wizard__step-graphic--right',
	]
		.filter( Boolean )
		.join( ' ' );

	return <div className={ className }>{ children }</div>;
}

function SetupWizardPreviewLink( { href, label } ) {
	if ( ! href ) {
		return null;
	}

	return (
		<a
			href={ href }
			target="_blank"
			rel="noopener noreferrer"
			className="complyops-setup-wizard__preview-link"
		>
			{ label }
		</a>
	);
}

function StepProgress( { step } ) {
	return (
		<p
			className="complyops-setup-wizard__progress"
			aria-live="polite"
		>
			{ sprintf(
				/* translators: 1: current step number, 2: total steps */
				__( 'Step %1$d of %2$d', 'complyops' ),
				step + 1,
				TOTAL_STEPS
			) }
		</p>
	);
}

export default function SetupWizardModal() {
	const canManage = !! window.complyopsAdmin?.canManage;
	const [ settings, setSettings ] = useState( null );
	const [ audit, setAudit ] = useState( null );
	const [ wizardOpen, setWizardOpen ] = useState( false );
	const [ step, setStep ] = useState( 0 );
	const [ running, setRunning ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ bannerDraft, setBannerDraft ] = useState( null );

	const load = useCallback( async () => {
		if ( ! canManage ) {
			return;
		}

		try {
			const [ loadedSettings, latestAudit ] = await Promise.all( [
				getSettings(),
				getLatestAudit().catch( () => null ),
			] );
			setSettings( loadedSettings );
			setAudit( latestAudit );

			const wizard = loadedSettings?.setup_wizard || {};
			const consent = loadedSettings?.consent || {};
			const consentPublic = loadedSettings?.consent_public || {};
			const shouldOpen =
				( wizard.show_wizard ?? wizard.show_prompt ?? false ) &&
				! isSetupSnoozed();

			setBannerDraft( ( current ) => {
				if ( current ) {
					return current;
				}

				return {
					enabled: wizard.banner_decided
						? !! consent.enabled
						: true,
					banner_headline: consent.banner_headline || '',
					banner_description: consent.banner_description || '',
					show_reopen_button:
						consent.show_reopen_button !== undefined
							? !! consent.show_reopen_button
							: true,
					show_site_logo: !! consent.show_site_logo,
					site_logo_url:
						consentPublic.banner?.site_logo_url || '',
					site_name: consentPublic.banner?.site_name || '',
				};
			} );

			if ( shouldOpen ) {
				setWizardOpen( true );
			}
		} catch {
			// Setup wizard is optional to dismiss for the session; ignore load failures.
		}
	}, [ canManage ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const saveSettings = async ( patch ) => {
		setSaving( true );
		setError( null );
		try {
			const saved = await updateSettings( patch );
			setSettings( saved );
			return saved;
		} catch ( err ) {
			setError(
				err?.message || __( 'Failed to save settings.', 'complyops' )
			);
			throw err;
		} finally {
			setSaving( false );
		}
	};

	const handleDismiss = () => {
		snoozeSetup();
		setWizardOpen( false );
		setError( null );
	};

	const handleBack = () => {
		if ( step <= 0 || running || saving ) {
			return;
		}
		setError( null );
		setStep( ( current ) => Math.max( 0, current - 1 ) );
	};

	const handleRunAuditAndAdvance = async () => {
		if ( audit ) {
			setStep( 4 );
			return;
		}

		setRunning( true );
		setError( null );
		try {
			await runAudit( 'gdpr' );
			const latestAudit = await getLatestAudit();
			setAudit( latestAudit );
			setStep( 4 );
		} catch ( err ) {
			setError( err?.message || __( 'Audit failed.', 'complyops' ) );
		} finally {
			setRunning( false );
		}
	};

	const saveBannerChoice = async () => {
		const draft = bannerDraft || { enabled: true };
		const enabled = !! draft.enabled;

		await saveSettings( {
			setup_banner_decided: true,
			consent: {
				...( settings?.consent || {} ),
				enabled,
				banner_headline: draft.banner_headline || '',
				banner_description: draft.banner_description || '',
				show_reopen_button: !! draft.show_reopen_button,
				show_site_logo: !! draft.show_site_logo,
			},
			enforcement: {
				...( settings?.enforcement || {} ),
				enabled,
			},
		} );
	};

	const handleNext = async () => {
		if ( running || saving ) {
			return;
		}

		setError( null );

		if ( step === 0 ) {
			setStep( 1 );
			return;
		}

		if ( step === 1 ) {
			await saveBannerChoice();
			setStep( 2 );
			return;
		}

		if ( step === 2 ) {
			setStep( 3 );
			return;
		}

		if ( step === 3 ) {
			await handleRunAuditAndAdvance();
			return;
		}

		if ( step === 4 ) {
			setStep( 5 );
			return;
		}

		await handleComplete();
	};

	const handleComplete = async () => {
		await saveSettings( { setup_complete: true } );
		clearSetupSnooze();
		setWizardOpen( false );
		setStep( 0 );
		window.dispatchEvent( new CustomEvent( 'complyops:setup-complete' ) );
	};

	if ( ! canManage ) {
		return null;
	}

	const monitoring = settings?.monitoring_interval || 'weekly';
	const publicStatus = settings?.public_status || {};
	const auditControlTotal = audit
		? ( audit.passed || 0 ) +
		  ( audit.failed || 0 ) +
		  ( audit.warnings || 0 ) +
		  ( audit.unknowns || 0 )
		: 0;
	const auditActionRequired = audit
		? ( audit.failed || 0 ) + ( audit.warnings || 0 )
		: 0;

	const savePublicStatus = async ( nextPublicStatus ) => {
		await saveSettings( {
			public_status: nextPublicStatus,
		} );
	};

	const updateBannerDraft = ( patch ) => {
		setBannerDraft( ( current ) => ( {
			...( current || { enabled: true } ),
			...patch,
		} ) );
	};

	const stepMeta = [
		{
			title: (
				<>
					{ __( 'Welcome to', 'complyops' ) }{ ' ' }
					<ComplyOpsLogo className="complyops-setup-prompt__title-logo" />
				</>
			),
			description: __(
				'Complete this short setup to establish technical GDPR readiness for this WordPress site.',
				'complyops'
			),
		},
		{
			title: __( 'Consent banner', 'complyops' ),
			description: __(
				'Edit the banner and choose whether to show it on your site.',
				'complyops'
			),
		},
		{
			title: __( 'Scheduled monitoring', 'complyops' ),
			description: __(
				'Choose how often ComplyOps scans your site so configuration drift is caught early.',
				'complyops'
			),
		},
		{
			title: __( 'First technical audit', 'complyops' ),
			description: __(
				'Establish a technical readiness baseline for ongoing monitoring.',
				'complyops'
			),
		},
		{
			title: __( 'Public compliance status', 'complyops' ),
			description: __(
				'Choose whether to share a read-only summary of your site’s technical compliance status with auditors, partners, or customers.',
				'complyops'
			),
		},
		{
			title: __( 'Review your results', 'complyops' ),
			description: __(
				'Review your audit results and learn where to find them later.',
				'complyops'
			),
		},
	];

	let wizardBody = null;

	if ( step === 0 ) {
		wizardBody = (
			<div className="complyops-setup-wizard__step">
				<p>
					{ __(
						'ComplyOps discovers privacy risks, audits technical controls, and helps you monitor configuration over time to prevent drift.',
						'complyops'
					) }
				</p>
				<p className="complyops-muted">
					{ __(
						'This short setup configures the consent banner, monitoring, runs your first audit, and optionally publishes public status. You can dismiss setup for this session and return later.',
						'complyops'
					) }
				</p>
				<ul className="complyops-checklist">
					<li>
						<span
							className="dashicons dashicons-privacy"
							aria-hidden="true"
						/>
						<span>
							{ __(
								'Approve or edit the consent banner',
								'complyops'
							) }
						</span>
					</li>
					<li>
						<span
							className="dashicons dashicons-clock"
							aria-hidden="true"
						/>
						<span>
							{ __( 'Set a monitoring schedule', 'complyops' ) }
						</span>
					</li>
					<li>
						<span
							className="dashicons dashicons-search"
							aria-hidden="true"
						/>
						<span>
							{ __( 'Run your first audit', 'complyops' ) }
						</span>
					</li>
					<li>
						<span
							className="dashicons dashicons-share"
							aria-hidden="true"
						/>
						<span>
							{ __(
								'Optionally publish public status',
								'complyops'
							) }
						</span>
					</li>
					<li>
						<span
							className="dashicons dashicons-chart-bar"
							aria-hidden="true"
						/>
						<span>
							{ __( 'Review readiness results', 'complyops' ) }
						</span>
					</li>
				</ul>
			</div>
		);
	} else if ( step === 1 ) {
		wizardBody = (
			<div className="complyops-setup-wizard__step complyops-setup-wizard__step--banner">
				<label className="complyops-setup-wizard__enable-banner">
					<input
						type="checkbox"
						checked={ !! bannerDraft?.enabled }
						onChange={ ( event ) =>
							updateBannerDraft( {
								enabled: event.target.checked,
							} )
						}
						disabled={ saving }
					/>{ ' ' }
					{ __( 'Enable the consent banner', 'complyops' ) }
				</label>
				<p className="complyops-muted complyops-setup-wizard__banner-help">
					{ __(
						'Recommended for GDPR readiness. Change anytime under Consent.',
						'complyops'
					) }
				</p>
				{ bannerDraft ? (
					<BannerDesigner
						compact
						headline={ bannerDraft.banner_headline || '' }
						description={ bannerDraft.banner_description || '' }
						showReopenButton={ bannerDraft.show_reopen_button }
						showSiteLogo={ bannerDraft.show_site_logo }
						siteLogoUrl={ bannerDraft.site_logo_url }
						siteName={ bannerDraft.site_name }
						canShowSiteLogo={ !! bannerDraft.site_logo_url }
						onHeadlineChange={ ( value ) =>
							updateBannerDraft( { banner_headline: value } )
						}
						onDescriptionChange={ ( value ) =>
							updateBannerDraft( { banner_description: value } )
						}
						onShowReopenChange={ ( value ) =>
							updateBannerDraft( {
								show_reopen_button: value,
							} )
						}
						onShowSiteLogoChange={ ( value ) =>
							updateBannerDraft( { show_site_logo: value } )
						}
					/>
				) : null }
			</div>
		);
	} else if ( step === 2 ) {
		wizardBody = (
			<div className="complyops-setup-wizard__step complyops-setup-wizard__step--monitoring">
				<SetupWizardStepGraphic>
					<SetupWizardMonitoringGraphic />
				</SetupWizardStepGraphic>
				<p className="complyops-muted">
					{ __(
						'Scheduled monitoring runs technical audits in the background, compares each result with your established baseline, and alerts administrators when controls change or regress.',
						'complyops'
					) }
				</p>
				<div className="complyops-setup-wizard__monitoring-control">
					<label className="complyops-field">
						<span>
							{ __( 'Monitoring interval', 'complyops' ) }
						</span>
						<select
							value={ monitoring }
							onChange={ ( event ) =>
								saveSettings( {
									monitoring_interval: event.target.value,
								} )
							}
							disabled={ saving }
						>
							<option value="disabled">
								{ __( 'Disabled', 'complyops' ) }
							</option>
							<option value="daily">
								{ __( 'Daily', 'complyops' ) }
							</option>
							<option value="weekly">
								{ __( 'Weekly', 'complyops' ) }
							</option>
							<option value="monthly">
								{ __( 'Monthly', 'complyops' ) }
							</option>
						</select>
					</label>
					<p className="complyops-muted">
						{ __(
							'Weekly is a good default for most sites. You can change this at any time in Monitoring Settings.',
							'complyops'
						) }
					</p>
				</div>
			</div>
		);
	} else if ( step === 3 ) {
		wizardBody = (
			<div className="complyops-setup-wizard__step">
				<SetupWizardStepGraphic>
					{ running ? (
						<>
							<span
								className="spinner is-active"
								aria-hidden="true"
							/>
							<p className="complyops-muted">
								{ __( 'Scanning site controls…', 'complyops' ) }
							</p>
						</>
					) : (
						<SetupWizardAuditGraphic />
					) }
				</SetupWizardStepGraphic>
				<p>
					{ __(
						'ComplyOps evaluates your site against its GDPR technical control catalog and calculates a readiness score.',
						'complyops'
					) }
				</p>
				<p className="complyops-muted">
					{ __(
						'The audit reviews WordPress settings, consent tools, forms, trackers, embeds, and detected third-party services. Your results identify controls that pass, need attention, or require manual review.',
						'complyops'
					) }
				</p>
				<p className="complyops-muted">
					{ __(
						'This is a technical readiness assessment, not a legal certification.',
						'complyops'
					) }
				</p>
				<p className="complyops-muted">
					{ __( 'Need to assess another framework?', 'complyops' ) }{ ' ' }
					<a
						href={ STORE_URL }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __(
							'Browse available framework packs in the ComplyOps marketplace.',
							'complyops'
						) }
					</a>
				</p>
			</div>
		);
	} else if ( step === 4 ) {
		wizardBody = (
			<div className="complyops-setup-wizard__step">
				<SetupWizardStepGraphic>
					<SetupWizardPublicStatusGraphic />
				</SetupWizardStepGraphic>
				<p>
					{ __(
						'You can publish a web page for people to view, a JSON API for other systems to use, or both.',
						'complyops'
					) }
				</p>
				<p className="complyops-muted">
					{ __(
						'Only a limited summary is published, including technical readiness, monitoring status, verification date, and high-severity finding counts. Raw audit results, evidence, configuration details, and personal data remain private. Published status is not a legal certification.',
						'complyops'
					) }
				</p>
				<div className="complyops-setup-wizard__publish-option">
					<div>
						<label className="complyops-field complyops-field--checkbox">
							<input
								type="checkbox"
								checked={ !! publicStatus.page_enabled }
								onChange={ ( event ) =>
									savePublicStatus( {
										...publicStatus,
										page_enabled: event.target.checked,
									} )
								}
								disabled={ saving }
							/>
							<span>
								{ __(
									'Publish a public status page',
									'complyops'
								) }
							</span>
						</label>
						<p className="complyops-muted complyops-setup-wizard__publish-help">
							{ __(
								'Creates a shareable web page showing your current compliance status.',
								'complyops'
							) }
						</p>
					</div>
					<SetupWizardPreviewLink
						href={
							publicStatus.page_preview_url ||
							publicStatus.page_url
						}
						label={ __( 'Preview status page', 'complyops' ) }
					/>
				</div>
				<div className="complyops-setup-wizard__publish-option">
					<div>
						<label className="complyops-field complyops-field--checkbox">
							<input
								type="checkbox"
								checked={ !! publicStatus.api_enabled }
								onChange={ ( event ) =>
									savePublicStatus( {
										...publicStatus,
										api_enabled: event.target.checked,
									} )
								}
								disabled={ saving }
							/>
							<span>
								{ __(
									'Publish a public status API',
									'complyops'
								) }
							</span>
						</label>
						<p className="complyops-muted complyops-setup-wizard__publish-help">
							{ __(
								'Makes the same status summary available as JSON for integrations and automated checks.',
								'complyops'
							) }
						</p>
					</div>
					<SetupWizardPreviewLink
						href={
							publicStatus.api_preview_url || publicStatus.api_url
						}
						label={ __( 'View API response', 'complyops' ) }
					/>
				</div>
				<p className="complyops-muted">
					{ __(
						'Both options are optional and disabled by default. Leave them off if you do not need to share your compliance status publicly.',
						'complyops'
					) }
				</p>
			</div>
		);
	} else {
		wizardBody = (
			<div className="complyops-setup-wizard__step">
				<p>
					{ __(
						'Your technical readiness score summarizes how many controls passed. It becomes the baseline for future audits and is not a measure of legal compliance.',
						'complyops'
					) }
				</p>
				{ audit && (
					<div className="complyops-summary-card complyops-setup-wizard__audit-summary">
						<div
							className="complyops-summary-card__metrics"
							role="list"
						>
							<div
								className="complyops-summary-card__item"
								role="listitem"
							>
								<span className="complyops-summary-card__label">
									{ __( 'Technical readiness', 'complyops' ) }
								</span>
								<span
									className={
										( audit.score ?? 0 ) >= 80
											? 'complyops-summary-card__value complyops-summary-card__value--numeric complyops-summary-card__value--success'
											: 'complyops-summary-card__value complyops-summary-card__value--numeric'
									}
								>
									{ `${ audit.score ?? 0 }%` }
								</span>
							</div>
							<div
								className="complyops-summary-card__item"
								role="listitem"
							>
								<span className="complyops-summary-card__label">
									{ __( 'Effective controls', 'complyops' ) }
								</span>
								<span className="complyops-summary-card__value complyops-summary-card__value--numeric">
									{ `${
										audit.passed || 0
									} / ${ auditControlTotal }` }
								</span>
							</div>
							<div
								className="complyops-summary-card__item"
								role="listitem"
							>
								<span className="complyops-summary-card__label">
									{ __( 'Action required', 'complyops' ) }
								</span>
								<span className="complyops-summary-card__value complyops-summary-card__value--numeric">
									{ auditActionRequired }
								</span>
							</div>
						</div>
					</div>
				) }
				<h3>{ __( 'What your results mean', 'complyops' ) }</h3>
				<ul className="complyops-list">
					<li>
						<div className="complyops-list__row">
							<StatusBadge status="PASS" />
							<p>
								{ __(
									'The control met the technical checks in this audit.',
									'complyops'
								) }
							</p>
						</div>
					</li>
					<li>
						<div className="complyops-list__row">
							<StatusBadge status="FAIL" />
							<p>
								{ __(
									'The control did not meet its test criteria and creates a Finding that needs attention.',
									'complyops'
								) }
							</p>
						</div>
					</li>
					<li>
						<div className="complyops-list__row">
							<StatusBadge status="WARNING" />
							<p>
								{ __(
									'ComplyOps detected a possible or partial issue that should be reviewed.',
									'complyops'
								) }
							</p>
						</div>
					</li>
					<li>
						<div className="complyops-list__row">
							<StatusBadge status="UNKNOWN" />
							<p>
								{ __(
									'ComplyOps could not verify the control automatically, so manual review may be required.',
									'complyops'
								) }
							</p>
						</div>
					</li>
				</ul>
				<p>
					{ __(
						'Findings turn results that need attention into a prioritized worklist with explanations, observed conditions, and recommended fixes when available.',
						'complyops'
					) }
				</p>
				<p className="complyops-muted">
					{ __(
						'To return later, open Audits. Use Overview for your latest score, Findings for control-by-control results, and History for previous audits.',
						'complyops'
					) }
				</p>
			</div>
		);
	}

	const nextLabel = (() => {
		if ( step === TOTAL_STEPS - 1 ) {
			return saving
				? __( 'Finishing…', 'complyops' )
				: __( 'Finish setup', 'complyops' );
		}
		if ( step === 3 && ! audit ) {
			return running
				? __( 'Running audit…', 'complyops' )
				: __( 'Run audit & next', 'complyops' );
		}
		return __( 'Next', 'complyops' );
	})();

	const wizardFooter = (
		<>
			<div className="complyops-product-tutorial__dismiss-actions">
				<button
					type="button"
					className="button button-secondary"
					onClick={ handleDismiss }
					disabled={ running || saving }
				>
					{ __( 'Dismiss', 'complyops' ) }
				</button>
			</div>
			<div className="complyops-dialog__footer-actions">
				<button
					type="button"
					className="button button-secondary"
					onClick={ handleBack }
					disabled={ step === 0 || running || saving }
				>
					{ __( 'Back', 'complyops' ) }
				</button>
				<button
					type="button"
					className="button button-primary"
					onClick={ handleNext }
					disabled={ running || saving }
				>
					{ nextLabel }
				</button>
			</div>
		</>
	);

	const dialogSize =
		step === 1 || step === 4 || step === 5 ? 'wide' : 'default';

	return (
		<Dialog
			isOpen={ wizardOpen }
			onClose={ handleDismiss }
			title={ stepMeta[ step ].title }
			description={ stepMeta[ step ].description }
			panelClassName="complyops-setup-wizard__panel"
			headerAside={ <StepProgress step={ step } /> }
			size={ dialogSize }
			showCloseButton={ false }
			closeOnBackdrop={ false }
			disableEscape
			footer={ wizardFooter }
		>
			<div className="complyops-dialog__body complyops-setup-wizard">
				{ error && <Alert tone="danger">{ error }</Alert> }
				{ wizardBody }
			</div>
		</Dialog>
	);
}
