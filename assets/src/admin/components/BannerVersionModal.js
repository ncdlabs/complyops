import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	BANNER_DIFF_FIELDS,
	fieldChanged,
	formatBooleanValue,
	formatRevisionDate,
	snapshotFromRevision,
	snapshotFromSettings,
} from './banner-diff';
import { ReadOnlyBannerPreview } from './banner-preview';
import { ConfirmDialog, Dialog } from './Dialog';

function DiffFieldValue( { field, value } ) {
	if ( field.type === 'boolean' ) {
		return <span>{ formatBooleanValue( value ) }</span>;
	}

	if ( ! value ) {
		return (
			<span className="complyops-muted">
				{ __( '(empty)', 'complyops' ) }
			</span>
		);
	}

	return (
		<div
			className="complyops-banner-version-modal__html"
			// nosemgrep: typescript.react.security.audit.react-dangerouslysetinnerhtml.react-dangerouslysetinnerhtml -- Read-only diff view of stored banner HTML.
			dangerouslySetInnerHTML={ { __html: value } }
		/>
	);
}

function DiffRow( { field, leftValue, rightValue } ) {
	const changed = fieldChanged( leftValue, rightValue, field );

	return (
		<div className="complyops-banner-version-modal__diff-row">
			<div className="complyops-banner-version-modal__diff-label">
				{ field.label }
			</div>
			<div className="complyops-banner-version-modal__diff-columns">
				<div
					className={ `complyops-banner-version-modal__diff-cell complyops-banner-version-modal__diff-cell--previous${
						changed ? ' is-changed' : ''
					}` }
				>
					<DiffFieldValue field={ field } value={ leftValue } />
				</div>
				<div
					className={ `complyops-banner-version-modal__diff-cell complyops-banner-version-modal__diff-cell--current${
						changed ? ' is-changed' : ''
					}` }
				>
					<DiffFieldValue field={ field } value={ rightValue } />
				</div>
			</div>
		</div>
	);
}

export default function BannerVersionModal( {
	isOpen,
	onClose,
	settings,
	revisions,
	siteLogoUrl,
	siteName,
	canShowSiteLogo,
	onRevert,
	revertingId,
} ) {
	const revisionList = useMemo(
		() => ( Array.isArray( revisions ) ? revisions : [] ),
		[ revisions ]
	);
	const [ selectedRevisionId, setSelectedRevisionId ] = useState( '' );
	const [ previewOpen, setPreviewOpen ] = useState( false );
	const [ confirmRevertOpen, setConfirmRevertOpen ] = useState( false );

	const currentSnapshot = useMemo(
		() => snapshotFromSettings( settings || {} ),
		[ settings ]
	);

	const selectedRevision = useMemo(
		() =>
			revisionList.find(
				( revision ) => revision.id === selectedRevisionId
			) || null,
		[ revisionList, selectedRevisionId ]
	);

	const previousSnapshot = useMemo(
		() =>
			selectedRevision ? snapshotFromRevision( selectedRevision ) : null,
		[ selectedRevision ]
	);

	useEffect( () => {
		if ( ! isOpen ) {
			setPreviewOpen( false );
			setConfirmRevertOpen( false );
			return;
		}

		if ( revisionList.length > 0 ) {
			setSelectedRevisionId( ( current ) => {
				if (
					current &&
					revisionList.some( ( revision ) => revision.id === current )
				) {
					return current;
				}

				return revisionList[ 0 ].id;
			} );
		} else {
			setSelectedRevisionId( '' );
		}
	}, [ isOpen, revisionList ] );

	useEffect( () => {
		if ( ! isOpen ) {
			return undefined;
		}

		const onKeyDown = ( event ) => {
			if ( event.key !== 'Escape' ) {
				return;
			}

			if ( confirmRevertOpen ) {
				setConfirmRevertOpen( false );
				return;
			}

			if ( previewOpen ) {
				setPreviewOpen( false );
				return;
			}

			onClose();
		};

		document.addEventListener( 'keydown', onKeyDown );

		return () => {
			document.removeEventListener( 'keydown', onKeyDown );
		};
	}, [ confirmRevertOpen, isOpen, onClose, previewOpen ] );

	const handleRevertConfirm = () => {
		if ( ! selectedRevision ) {
			return;
		}

		onRevert( selectedRevision.id );
		setConfirmRevertOpen( false );
	};

	const previewShowSiteLogo =
		canShowSiteLogo && previousSnapshot?.show_site_logo;
	const isRevertingSelected = revertingId === selectedRevisionId;

	return (
		<>
			<Dialog
				isOpen={ isOpen }
				onClose={ onClose }
				disableEscape
				title={ __( 'Banner Version History', 'complyops' ) }
				titleId="complyops-banner-version-modal-title"
				description={ sprintf(
					/* translators: %s: current consent banner version */
					__( 'Current Version: %s', 'complyops' ),
					settings?.version || '—'
				) }
				size="wide"
				footer={
					<>
						<button
							type="button"
							className="button button-secondary"
							onClick={ onClose }
						>
							{ __( 'Close', 'complyops' ) }
						</button>
						<div className="complyops-dialog__footer-actions">
							<button
								type="button"
								className="button button-secondary"
								disabled={ ! selectedRevision }
								onClick={ () => setPreviewOpen( true ) }
							>
								{ __(
									'Preview previous version',
									'complyops'
								) }
							</button>
							<button
								type="button"
								className="button button-primary"
								disabled={
									! selectedRevision || isRevertingSelected
								}
								onClick={ () => setConfirmRevertOpen( true ) }
							>
								{ isRevertingSelected
									? __( 'Reverting…', 'complyops' )
									: __(
											'Revert to selected version',
											'complyops'
									  ) }
							</button>
						</div>
					</>
				}
			>
				{ revisionList.length === 0 ? (
					<div className="complyops-banner-version-modal__empty">
						<p>
							{ __(
								'No previous banner versions yet. Saving banner changes creates a revision you can compare and restore here.',
								'complyops'
							) }
						</p>
					</div>
				) : (
					<>
						<div className="complyops-banner-version-modal__selector">
							<label htmlFor="complyops-banner-version-select">
								{ __(
									'Compare With Previous Version',
									'complyops'
								) }
							</label>
							<select
								id="complyops-banner-version-select"
								value={ selectedRevisionId }
								onChange={ ( event ) =>
									setSelectedRevisionId( event.target.value )
								}
							>
								{ revisionList.map( ( revision ) => (
									<option
										key={ revision.id }
										value={ revision.id }
									>
										{ sprintf(
											/* translators: 1: version number, 2: saved date */
											__(
												'Version %1$s — %2$s',
												'complyops'
											),
											revision.version || '—',
											formatRevisionDate(
												revision.saved_at
											)
										) }
									</option>
								) ) }
							</select>
						</div>

						<div className="complyops-banner-version-modal__diff-headings">
							<span>
								{ __( 'Previous Version', 'complyops' ) }
							</span>
							<span>
								{ __( 'Current Version', 'complyops' ) }
							</span>
						</div>

						<div className="complyops-banner-version-modal__diff">
							{ BANNER_DIFF_FIELDS.map( ( field ) => (
								<DiffRow
									key={ field.key }
									field={ field }
									leftValue={
										previousSnapshot?.[ field.key ]
									}
									rightValue={ currentSnapshot[ field.key ] }
								/>
							) ) }
						</div>
					</>
				) }
			</Dialog>

			<Dialog
				isOpen={ isOpen && previewOpen && !! previousSnapshot }
				onClose={ () => setPreviewOpen( false ) }
				disableEscape
				title={ sprintf(
					/* translators: %s: consent banner version number */
					__( 'Preview Version %s', 'complyops' ),
					selectedRevision?.version || '—'
				) }
				titleId="complyops-banner-version-preview-title"
				description={ __(
					'This is how visitors would see the selected banner version.',
					'complyops'
				) }
				size="preview"
				layer="nested"
				closeLabel={ __( 'Close preview', 'complyops' ) }
				footer={
					<button
						type="button"
						className="button button-secondary"
						onClick={ () => setPreviewOpen( false ) }
					>
						{ __( 'Close preview', 'complyops' ) }
					</button>
				}
			>
				<ReadOnlyBannerPreview
					headline={ previousSnapshot?.banner_headline }
					description={ previousSnapshot?.banner_description }
					showSiteLogo={ previewShowSiteLogo }
					siteLogoUrl={ siteLogoUrl }
					siteName={ siteName }
				/>
			</Dialog>

			<ConfirmDialog
				isOpen={ confirmRevertOpen }
				onCancel={ () => setConfirmRevertOpen( false ) }
				onConfirm={ handleRevertConfirm }
				title={ __( 'Revert Banner Version?', 'complyops' ) }
				message={ sprintf(
					/* translators: %s: consent banner version number */
					__(
						'Restore version %s? This saves immediately and bumps the consent version so visitors may be asked again.',
						'complyops'
					),
					selectedRevision?.version || '—'
				) }
				confirmLabel={ __( 'Revert version', 'complyops' ) }
				cancelLabel={ __( 'Cancel', 'complyops' ) }
				confirming={ isRevertingSelected }
				tone="danger"
				disableEscape
			/>
		</>
	);
}
