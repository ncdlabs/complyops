import { useCallback, useEffect, useId, useLayoutEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getSettings, updateSettings } from '../api';
import { adminPageUrl, getQueryParam } from '../url-state';
import { Alert } from './ui';

const TUTORIAL_SNOOZE_KEY = 'complyops_tutorial_prompt_snoozed';
const TUTORIAL_STEP_KEY = 'complyops_tutorial_active_step';
const TOTAL_STEPS = 6;
const POPOVER_WIDTH = 420;
const TARGET_PAD = 6;

function isPromptSnoozed() {
	try {
		return sessionStorage.getItem( TUTORIAL_SNOOZE_KEY ) === '1';
	} catch {
		return false;
	}
}

function snoozePrompt() {
	try {
		sessionStorage.setItem( TUTORIAL_SNOOZE_KEY, '1' );
	} catch {
		// Ignore storage failures.
	}
}

function clearPromptSnooze() {
	try {
		sessionStorage.removeItem( TUTORIAL_SNOOZE_KEY );
	} catch {
		// Ignore storage failures.
	}
}

function readStoredStep() {
	try {
		const raw = sessionStorage.getItem( TUTORIAL_STEP_KEY );
		if ( raw === null ) {
			return 0;
		}
		const value = Number.parseInt( raw, 10 );
		if ( Number.isNaN( value ) || value < 0 || value >= TOTAL_STEPS ) {
			return 0;
		}
		return value;
	} catch {
		return 0;
	}
}

function storeStep( step ) {
	try {
		sessionStorage.setItem( TUTORIAL_STEP_KEY, String( step ) );
	} catch {
		// Ignore storage failures.
	}
}

function clearStoredStep() {
	try {
		sessionStorage.removeItem( TUTORIAL_STEP_KEY );
	} catch {
		// Ignore storage failures.
	}
}

function currentAdminPageSlug() {
	return getQueryParam( 'page' ) || 'complyops';
}

function ensureRightMenuExpanded() {
	const body = document.body;
	if ( ! body?.classList.contains( 'complyops-right-menu-collapsed' ) ) {
		return;
	}
	document.getElementById( 'complyops-right-menu-toggle' )?.click();
}

function queryFirst( selectors ) {
	for ( const selector of selectors ) {
		const el = document.querySelector( selector );
		if ( el ) {
			return el;
		}
	}
	return null;
}

function queryAll( selectors ) {
	const seen = new Set();
	const elements = [];
	for ( const selector of selectors ) {
		document.querySelectorAll( selector ).forEach( ( el ) => {
			if ( seen.has( el ) ) {
				return;
			}
			seen.add( el );
			elements.push( el );
		} );
	}
	return elements;
}

function rectsForElements( elements ) {
	return elements
		.map( ( el ) => {
			const rect = el.getBoundingClientRect();
			if ( rect.width < 1 && rect.height < 1 ) {
				return null;
			}
			return {
				top: rect.top,
				left: rect.left,
				width: rect.width,
				height: rect.height,
				right: rect.right,
				bottom: rect.bottom,
			};
		} )
		.filter( Boolean );
}

function computePopoverPosition( anchor, popoverHeight ) {
	const gap = 14;
	const vw = window.innerWidth;
	const vh = window.innerHeight;
	const height = popoverHeight || 280;
	const width = POPOVER_WIDTH;

	if ( ! anchor ) {
		return {
			top: Math.max( 24, ( vh - height ) / 2 ),
			left: Math.max( 24, ( vw - width ) / 2 ),
		};
	}

	let left = anchor.left - width - gap;
	let top = anchor.top;

	if ( left < 16 ) {
		left = anchor.right + gap;
	}
	if ( left + width > vw - 16 ) {
		left = Math.max( 16, Math.min( anchor.left, vw - width - 16 ) );
		top = anchor.bottom + gap;
	}
	if ( top + height > vh - 16 ) {
		top = Math.max( 16, vh - height - 16 );
	}
	if ( top < 16 ) {
		top = 16;
	}
	if ( left < 16 ) {
		left = 16;
	}
	if ( left + width > vw - 16 ) {
		left = Math.max( 16, vw - width - 16 );
	}

	return { top, left };
}

function getSteps() {
	return [
		{
			title: __( 'Welcome tour', 'complyops' ),
			description: __(
				'A short tour of where to monitor, manage, and assure technical compliance.',
				'complyops'
			),
			body: [
				__(
					'Setup is complete. This tour shows how the ComplyOps admin is organized so you can find audits, controls, consent, and evidence quickly.',
					'complyops'
				),
				__(
					'You can dismiss this tour for now, dismiss it forever, or step through each area.',
					'complyops'
				),
			],
			page: null,
			target: [
				'#complyops-right-menu .complyops-right-menu__brand',
				'#complyops-right-menu',
			],
			highlights: [
				'#complyops-right-menu .complyops-right-menu__brand',
				'[data-complyops-nav-section]',
			],
		},
		{
			title: __( 'Monitor: Dashboard', 'complyops' ),
			description: __(
				'Start here for technical readiness and recent audit health.',
				'complyops'
			),
			body: [
				__(
					'The Dashboard summarizes your latest readiness score, monitoring status, and high-priority findings.',
					'complyops'
				),
				__(
					'Use Run audit from the dashboard when you want a fresh technical check-in. Deep links take you into Audits when something needs attention.',
					'complyops'
				),
			],
			page: 'complyops',
			target: [
				'[data-complyops-tour="run-audit"]',
				'[data-complyops-nav="complyops"]',
				'.complyops-page-header',
			],
			highlights: [
				'[data-complyops-nav="complyops"]',
				'[data-complyops-tour="run-audit"]',
				'.complyops-page-header',
			],
		},
		{
			title: __( 'Manage: Controls & Consent', 'complyops' ),
			description: __(
				'Configure controls, integrations, consent, and evidence from the Manage section.',
				'complyops'
			),
			body: [
				__(
					'Controls lists the catalog and applicability for each framework. Integrations connects analytics, GTM, and browser verification.',
					'complyops'
				),
				__(
					'Consent designs your banner and categories. Evidence stores proof used for audits and exports.',
					'complyops'
				),
			],
			page: 'complyops-consent',
			target: [
				'[data-complyops-tour="consent-enable"]',
				'[data-complyops-nav="complyops-consent"]',
				'.complyops-page-header',
			],
			highlights: [
				'[data-complyops-nav-section="manage"]',
				'[data-complyops-nav="complyops-controls"]',
				'[data-complyops-nav="complyops-integrations"]',
				'[data-complyops-nav="complyops-consent"]',
				'[data-complyops-nav="complyops-evidence"]',
				'[data-complyops-tour="consent-enable"]',
			],
		},
		{
			title: __( 'Control frameworks', 'complyops' ),
			description: __(
				'Add, activate, and expand the frameworks ComplyOps evaluates on this site.',
				'complyops'
			),
			body: [
				__(
					'Open Manage → Controls to see installed packs. Activate or deactivate a framework, then open View controls to enable or disable individual controls and set scope.',
					'complyops'
				),
				__(
					'Built-in packs such as GDPR ship with the plugin. Purchase additional compliance packs from the ComplyOps store on ncdLabs.com, then use Install New Framework to upload the encrypted .complyops-pack file and unlock key from your order.',
					'complyops'
				),
			],
			page: 'complyops-controls',
			target: [
				'[data-complyops-tour="install-framework"]',
				'[data-complyops-tour="controls-packs"]',
				'[data-complyops-nav="complyops-controls"]',
			],
			highlights: [
				'[data-complyops-nav="complyops-controls"]',
				'[data-complyops-tour="controls-packs"]',
				'[data-complyops-tour="install-framework"]',
			],
		},
		{
			title: __( 'Assure: Audits & Reports', 'complyops' ),
			description: __(
				'Run audits, work findings, and export readiness reports.',
				'complyops'
			),
			body: [
				__(
					'Audits combines Overview, Findings, and History. Findings is the prioritized worklist for failed or warning controls.',
					'complyops'
				),
				__(
					'Reports produces a full technical readiness report you can print or export.',
					'complyops'
				),
			],
			page: 'complyops-audit',
			target: [
				'[data-complyops-tour="run-audit"]',
				'[data-complyops-nav="complyops-audit"]',
				'.complyops-page-header',
			],
			highlights: [
				'[data-complyops-nav-section="assure"]',
				'[data-complyops-nav="complyops-audit"]',
				'[data-complyops-nav="complyops-reports"]',
				'[data-complyops-tour="run-audit"]',
				'.complyops-tabs',
			],
		},
		{
			title: __( 'You’re ready', 'complyops' ),
			description: __(
				'Use the right-side menu anytime. ComplyOps measures technical readiness, not legal certification.',
				'complyops'
			),
			body: [
				__(
					'Keep monitoring enabled, review findings after each audit, and update consent or integrations when your site changes.',
					'complyops'
				),
				__(
					'ComplyOps helps implement, verify, monitor, and document technical compliance controls. It does not provide legal advice or certify legal compliance.',
					'complyops'
				),
			],
			page: 'complyops',
			target: [
				'#complyops-right-menu',
				'[data-complyops-nav="complyops"]',
			],
			highlights: [
				'#complyops-right-menu .complyops-right-menu__brand',
				'[data-complyops-nav]',
			],
		},
	];
}

function navigateIfNeeded( pageSlug, stepIndex ) {
	if ( ! pageSlug ) {
		return false;
	}
	const current = currentAdminPageSlug();
	if ( current === pageSlug ) {
		return false;
	}
	storeStep( stepIndex );
	window.location.assign( adminPageUrl( pageSlug ) );
	return true;
}

export default function ProductTutorialModal() {
	const canManage = !! window.complyopsAdmin?.canManage;
	const titleId = useId();
	const popoverRef = useRef( null );
	const [ open, setOpen ] = useState( false );
	const [ step, setStep ] = useState( 0 );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ anchorRects, setAnchorRects ] = useState( [] );
	const [ popoverPos, setPopoverPos ] = useState( { top: 24, left: 24 } );

	const load = useCallback( async () => {
		if ( ! canManage ) {
			return;
		}

		try {
			const settings = await getSettings();
			const setupComplete = !! settings?.setup_wizard?.complete;
			const tutorial = settings?.tutorial || {};
			const shouldShow =
				setupComplete && tutorial.show && ! isPromptSnoozed();

			if ( ! shouldShow ) {
				setOpen( false );
				return;
			}

			const resumeStep = readStoredStep();
			setStep( resumeStep );
			setOpen( true );
			storeStep( resumeStep );

			const steps = getSteps();
			const resumePage = steps[ resumeStep ]?.page;
			if ( resumePage && currentAdminPageSlug() !== resumePage ) {
				navigateIfNeeded( resumePage, resumeStep );
			}
		} catch {
			// Tutorial is optional; ignore load failures.
		}
	}, [ canManage ] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		const onSetupComplete = () => {
			clearPromptSnooze();
			clearStoredStep();
			load();
		};

		window.addEventListener( 'complyops:setup-complete', onSetupComplete );
		return () => {
			window.removeEventListener(
				'complyops:setup-complete',
				onSetupComplete
			);
		};
	}, [ load ] );

	const measure = useCallback( () => {
		if ( ! open ) {
			return;
		}

		ensureRightMenuExpanded();
		const current = getSteps()[ step ];
		if ( ! current ) {
			return;
		}

		const highlightEls = queryAll( current.highlights || [] );
		const targetEl =
			queryFirst( current.target || [] ) || highlightEls[ 0 ] || null;

		if ( targetEl ) {
			targetEl.scrollIntoView( {
				block: 'nearest',
				inline: 'nearest',
				behavior: 'smooth',
			} );
		}

		const rects = rectsForElements(
			targetEl ? [ targetEl, ...highlightEls ] : highlightEls
		);
		setAnchorRects( rects );

		const anchor = rects[ 0 ] || null;
		const popoverHeight = popoverRef.current?.offsetHeight || 280;
		setPopoverPos( computePopoverPosition( anchor, popoverHeight ) );
	}, [ open, step ] );

	useLayoutEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		let cancelled = false;
		let tries = 0;
		const maxTries = 20;

		const tick = () => {
			if ( cancelled ) {
				return;
			}
			measure();
			tries += 1;
			const current = getSteps()[ step ];
			const found = queryFirst( [
				...( current?.target || [] ),
				...( current?.highlights || [] ),
			] );
			if ( ! found && tries < maxTries ) {
				window.setTimeout( tick, 100 );
			}
		};

		tick();

		const onReposition = () => measure();
		window.addEventListener( 'resize', onReposition );
		window.addEventListener( 'scroll', onReposition, true );

		return () => {
			cancelled = true;
			window.removeEventListener( 'resize', onReposition );
			window.removeEventListener( 'scroll', onReposition, true );
		};
	}, [ open, step, measure ] );

	const saveSettings = async ( patch ) => {
		setSaving( true );
		setError( null );
		try {
			await updateSettings( patch );
		} catch ( err ) {
			setError(
				err?.message || __( 'Failed to save settings.', 'complyops' )
			);
			throw err;
		} finally {
			setSaving( false );
		}
	};

	const closeTour = () => {
		setOpen( false );
		setStep( 0 );
		setError( null );
		setAnchorRects( [] );
		clearStoredStep();
	};

	const handleDismiss = () => {
		snoozePrompt();
		closeTour();
	};

	const handleDismissForever = async () => {
		await saveSettings( { tutorial_dismissed_forever: true } );
		clearPromptSnooze();
		closeTour();
	};

	const goToStep = ( nextStep ) => {
		const steps = getSteps();
		const clamped = Math.max( 0, Math.min( TOTAL_STEPS - 1, nextStep ) );
		storeStep( clamped );
		if ( navigateIfNeeded( steps[ clamped ]?.page, clamped ) ) {
			return;
		}
		setStep( clamped );
	};

	const handleBack = () => {
		if ( step <= 0 || saving ) {
			return;
		}
		goToStep( step - 1 );
	};

	const handleNext = async () => {
		if ( saving ) {
			return;
		}

		if ( step < TOTAL_STEPS - 1 ) {
			goToStep( step + 1 );
			return;
		}

		await saveSettings( { tutorial_complete: true } );
		clearPromptSnooze();
		closeTour();
	};

	if ( ! canManage || ! open ) {
		return null;
	}

	const current = getSteps()[ step ];
	const isLast = step === TOTAL_STEPS - 1;
	const primaryRect = anchorRects[ 0 ];

	return (
		<div className="complyops-tour" role="presentation">
			<div
				className={
					primaryRect
						? 'complyops-tour__overlay complyops-tour__overlay--clear'
						: 'complyops-tour__overlay'
				}
				aria-hidden="true"
			/>
			{ anchorRects.map( ( rect, index ) => (
				<div
					key={ `${ rect.left }-${ rect.top }-${ index }` }
					className={
						index === 0
							? 'complyops-tour__spotlight complyops-tour__spotlight--primary'
							: 'complyops-tour__spotlight'
					}
					style={ {
						top: Math.max( 0, rect.top - TARGET_PAD ),
						left: Math.max( 0, rect.left - TARGET_PAD ),
						width: rect.width + TARGET_PAD * 2,
						height: rect.height + TARGET_PAD * 2,
					} }
					aria-hidden="true"
				/>
			) ) }
			<div
				ref={ popoverRef }
				className="complyops-tour__popover"
				role="dialog"
				aria-modal="true"
				aria-labelledby={ titleId }
				style={ {
					top: popoverPos.top,
					left: popoverPos.left,
					width: POPOVER_WIDTH,
				} }
			>
				<header className="complyops-tour__header">
					<div className="complyops-tour__header-top">
						<div className="complyops-tour__header-copy">
							<h2 id={ titleId }>{ current.title }</h2>
							{ current.description && (
								<p className="complyops-muted">
									{ current.description }
								</p>
							) }
						</div>
						<p
							className="complyops-product-tutorial__progress"
							aria-live="polite"
						>
							{ sprintf(
								/* translators: 1: current step number, 2: total steps */
								__( 'Step %1$d of %2$d', 'complyops' ),
								step + 1,
								TOTAL_STEPS
							) }
						</p>
					</div>
				</header>
				<div className="complyops-tour__body complyops-product-tutorial">
					{ error && <Alert tone="danger">{ error }</Alert> }
					{ current.body.map( ( paragraph ) => (
						<p key={ paragraph }>{ paragraph }</p>
					) ) }
					{ ! primaryRect && (
						<p className="complyops-muted">
							{ __(
								'Looking for the highlighted area on this page…',
								'complyops'
							) }
						</p>
					) }
				</div>
				<footer className="complyops-tour__footer">
					<div className="complyops-product-tutorial__dismiss-actions">
						<button
							type="button"
							className="button button-link complyops-product-tutorial__dismiss"
							onClick={ handleDismissForever }
							disabled={ saving }
						>
							{ __( 'Dismiss forever', 'complyops' ) }
						</button>
						<button
							type="button"
							className="button button-link complyops-product-tutorial__dismiss"
							onClick={ handleDismiss }
							disabled={ saving }
						>
							{ __( 'Dismiss', 'complyops' ) }
						</button>
					</div>
					<div className="complyops-tour__footer-actions">
						<button
							type="button"
							className="button button-secondary"
							onClick={ handleBack }
							disabled={ step === 0 || saving }
						>
							{ __( 'Back', 'complyops' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ handleNext }
							disabled={ saving }
						>
							{ isLast
								? saving
									? __( 'Finishing…', 'complyops' )
									: __( 'Finish', 'complyops' )
								: __( 'Next', 'complyops' ) }
						</button>
					</div>
				</footer>
			</div>
		</div>
	);
}
