import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	LoadingState,
	ErrorState,
	PageHeader,
	SaveFooter,
	FormPage,
	isFormDirty,
	useNotify,
} from '../components/ui';
import BannerDesigner from '../components/BannerDesigner';
import BannerVersionModal from '../components/BannerVersionModal';

const NS = 'complyops/v1';

export default function ConsentPage( { config } ) {
	const [ settings, setSettings ] = useState( null );
	const [ baseline, setBaseline ] = useState( null );
	const [ siteLogoUrl, setSiteLogoUrl ] = useState( '' );
	const [ siteName, setSiteName ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ revertingId, setRevertingId ] = useState( null );
	const [ versionModalOpen, setVersionModalOpen ] = useState( false );
	const notify = useNotify();

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const data = await apiFetch( { path: `/${ NS }/settings` } );
			const consent = data.consent || {};
			setSettings( consent );
			setBaseline( consent );
			setSiteLogoUrl( data.consent_public?.banner?.site_logo_url || '' );
			setSiteName( data.consent_public?.banner?.site_name || '' );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to load consent settings.', 'complyops' )
			);
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const updateField = ( field, value ) => {
		setSettings( ( current ) => ( { ...current, [ field ]: value } ) );
	};

	const save = async () => {
		if ( ! config.canRemediate && ! config.canRunAudit ) {
			return;
		}

		setSaving( true );
		setError( null );

		try {
			const data = await apiFetch( {
				path: `/${ NS }/settings`,
				method: 'PUT',
				data: { consent: settings },
			} );
			const saved = data.consent || settings;
			setSettings( saved );
			setBaseline( saved );
			setSiteLogoUrl(
				data.consent_public?.banner?.site_logo_url || siteLogoUrl
			);
			setSiteName( data.consent_public?.banner?.site_name || siteName );
			notify.success( __( 'Consent settings saved.', 'complyops' ) );
		} catch ( err ) {
			notify.error(
				err?.message || __( 'Failed to save settings.', 'complyops' )
			);
		} finally {
			setSaving( false );
		}
	};

	const revertRevision = async ( revisionId ) => {
		if ( ! config.canRemediate && ! config.canRunAudit ) {
			return;
		}

		setRevertingId( revisionId );
		setError( null );

		try {
			const data = await apiFetch( {
				path: `/${ NS }/settings`,
				method: 'PUT',
				data: { consent: { revert_revision_id: revisionId } },
			} );
			const saved = data.consent || settings;
			setSettings( saved );
			setBaseline( saved );
			setSiteLogoUrl(
				data.consent_public?.banner?.site_logo_url || siteLogoUrl
			);
			setSiteName( data.consent_public?.banner?.site_name || siteName );
			notify.success( __( 'Banner revision restored.', 'complyops' ) );
			setVersionModalOpen( false );
		} catch ( err ) {
			notify.error(
				err?.message ||
					__( 'Failed to revert banner revision.', 'complyops' )
			);
		} finally {
			setRevertingId( null );
		}
	};

	if ( loading ) {
		return (
			<LoadingState
				message={ __( 'Loading consent settings…', 'complyops' ) }
			/>
		);
	}

	if ( error && ! settings ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}

	const dirty = isFormDirty( baseline, settings );

	const cancel = () => {
		setSettings( baseline );
		setError( null );
	};

	return (
		<FormPage
			footer={
				<SaveFooter
					onSave={ save }
					onCancel={ cancel }
					saving={ saving }
					dirty={ dirty }
					saveLabel={ __( 'Save', 'complyops' ) }
				/>
			}
		>
			<div className="complyops-consent-page">
				<PageHeader
					eyebrow={ __( 'Manage', 'complyops' ) }
					title={ __( 'Consent', 'complyops' ) }
					description={ __(
						'Configure transparent visitor choices and the native consent experience.',
						'complyops'
					) }
				/>

				<div className="complyops-panel">
					<div className="complyops-panel__header">
						<div className="complyops-banner-designer__version-meta">
							<button
								type="button"
								className="complyops-banner-designer__version-button"
								onClick={ () => setVersionModalOpen( true ) }
								aria-haspopup="dialog"
							>
								{ sprintf(
									/* translators: %s: consent banner version label */
									__( 'Version %s', 'complyops' ),
									settings.version || '—'
								) }
							</button>
							<label className="complyops-banner-designer__checkbox complyops-banner-designer__checkbox--freeze">
								<input
									type="checkbox"
									checked={ !! settings.freeze_version }
									onChange={ ( event ) =>
										updateField(
											'freeze_version',
											event.target.checked
										)
									}
								/>{ ' ' }
								{ __( 'Freeze Version', 'complyops' ) }
							</label>
						</div>
					</div>
					<BannerDesigner
						headline={ settings.banner_headline || '' }
						description={ settings.banner_description || '' }
						showReopenButton={ settings.show_reopen_button }
						showSiteLogo={ settings.show_site_logo }
						siteLogoUrl={ siteLogoUrl }
						siteName={ siteName }
						canShowSiteLogo={ !! siteLogoUrl }
						onHeadlineChange={ ( value ) =>
							updateField( 'banner_headline', value )
						}
						onDescriptionChange={ ( value ) =>
							updateField( 'banner_description', value )
						}
						onShowReopenChange={ ( value ) =>
							updateField( 'show_reopen_button', value )
						}
						onShowSiteLogoChange={ ( value ) =>
							updateField( 'show_site_logo', value )
						}
					/>
					<div className="complyops-consent-page__general">
						<label data-complyops-tour="consent-enable">
							<input
								type="checkbox"
								checked={ !! settings.enabled }
								onChange={ ( e ) =>
									updateField( 'enabled', e.target.checked )
								}
							/>{ ' ' }
							{ __(
								'Enable Native Consent Manager',
								'complyops'
							) }
						</label>
						<p className="complyops-muted">
							{ __(
								'Shows a consent banner on the public site, collects visitor choices by category, and blocks nonessential scripts until consent is given. When another consent management platform is already active on this site, the native manager stays off automatically so visitors are not asked twice.',
								'complyops'
							) }
						</p>
					</div>
					<BannerVersionModal
						isOpen={ versionModalOpen }
						onClose={ () => setVersionModalOpen( false ) }
						settings={ settings }
						revisions={ settings.banner_revisions || [] }
						siteLogoUrl={ siteLogoUrl }
						siteName={ siteName }
						canShowSiteLogo={ !! siteLogoUrl }
						onRevert={ revertRevision }
						revertingId={ revertingId }
					/>
				</div>
			</div>
		</FormPage>
	);
}
