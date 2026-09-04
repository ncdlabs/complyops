import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	getControls,
	getFrameworks,
	activateFramework,
	deactivateFramework,
	updateControl,
} from '../api';
import InstallFrameworkModal from '../components/InstallFrameworkModal';
import { Dialog } from '../components/Dialog';
import { ControlWorkflowPanel } from '../components/ControlWorkflowPanel';
import { PackDisclaimer } from '../components/PackDisclaimer';
import {
	SeverityBadge,
	LoadingState,
	ErrorState,
	PageHeader,
	SectionHeader,
	DetailBackLink,
	StatusBadge,
	useNotify,
} from '../components/ui';
import { getQueryParam, setQueryParam } from '../url-state';

const canManage = !! window.complyopsAdmin?.canManage;

function DisableControlDialog( {
	control,
	isOpen,
	onCancel,
	onConfirm,
	confirming,
} ) {
	const [ comment, setComment ] = useState( '' );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		if ( isOpen ) {
			setComment( '' );
			setError( '' );
		}
	}, [ isOpen, control?.id ] );

	const handleConfirm = () => {
		if ( ! comment.trim() ) {
			setError(
				__(
					'A comment is required when disabling a control.',
					'complyops'
				)
			);
			return;
		}

		onConfirm( comment.trim() );
	};

	return (
		<Dialog
			isOpen={ isOpen }
			onClose={ onCancel }
			title={ __( 'Disable control', 'complyops' ) }
			description={
				control ? `${ control.id } · ${ control.title }` : ''
			}
			footer={
				<div className="complyops-dialog__footer-actions complyops-dialog__footer-actions--end">
					<button
						type="button"
						className="button button-secondary"
						onClick={ onCancel }
						disabled={ confirming }
					>
						{ __( 'Cancel', 'complyops' ) }
					</button>
					<button
						type="button"
						className="button button-primary complyops-dialog__confirm--danger"
						onClick={ handleConfirm }
						disabled={ confirming }
					>
						{ confirming
							? __( 'Saving…', 'complyops' )
							: __( 'Disable control', 'complyops' ) }
					</button>
				</div>
			}
		>
			<div className="complyops-dialog__body">
				<p>
					{ __(
						'Explain why this control should not be evaluated on this site. The comment will appear in the controls table.',
						'complyops'
					) }
				</p>
				<label
					className="screen-reader-text"
					htmlFor="complyops-disable-comment"
				>
					{ __( 'Disable comment', 'complyops' ) }
				</label>
				<textarea
					id="complyops-disable-comment"
					className="large-text"
					rows={ 4 }
					value={ comment }
					onChange={ ( event ) => {
						setComment( event.target.value );
						if ( error ) {
							setError( '' );
						}
					} }
					placeholder={ __(
						'Required reason for disabling this control…',
						'complyops'
					) }
				/>
				{ error && (
					<p className="complyops-field-error" role="alert">
						{ error }
					</p>
				) }
			</div>
		</Dialog>
	);
}

function PackListView( {
	packs,
	loading,
	error,
	onRetry,
	onOpenPack,
	onTogglePack,
	togglingPackId,
	onInstall,
} ) {
	if ( loading && ! packs.length ) {
		return (
			<LoadingState
				message={ __( 'Loading framework packs…', 'complyops' ) }
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
				title={ __( 'Controls', 'complyops' ) }
				description={ __(
					'Activate compliance packs and choose which controls ComplyOps evaluates on this site.',
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

			<div
				className="complyops-panel complyops-panel--flush"
				data-complyops-tour="controls-packs"
			>
				<div className="complyops-table-wrap">
					<table className="widefat striped complyops-table complyops-table--packs">
						<thead>
							<tr>
								<th>{ __( 'Framework', 'complyops' ) }</th>
								<th>{ __( 'Version', 'complyops' ) }</th>
								<th>{ __( 'Controls', 'complyops' ) }</th>
								<th>{ __( 'Pack status', 'complyops' ) }</th>
								<th>{ __( 'Actions', 'complyops' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ packs.map( ( pack ) => (
								<tr key={ pack.id }>
									<td>
										<strong>{ pack.label }</strong>
										<div className="complyops-muted">
											{ pack.builtin
												? __( 'Built in', 'complyops' )
												: __(
														'Installed pack',
														'complyops'
												  ) }
										</div>
									</td>
									<td>{ pack.version }</td>
									<td>
										{ pack.enabled_control_count } /{ ' ' }
										{ pack.control_count }
										{ pack.disabled_control_count > 0 && (
											<div className="complyops-muted">
												{ pack.disabled_control_count }{ ' ' }
												{ __(
													'disabled',
													'complyops'
												) }
											</div>
										) }
									</td>
									<td>
										<StatusBadge
											status={
												pack.active ? 'PASS' : 'UNKNOWN'
											}
											label={
												pack.active
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
									</td>
									<td className="complyops-table__actions">
										<button
											type="button"
											className="button button-link"
											onClick={ () =>
												onOpenPack( pack.id )
											}
										>
											{ __(
												'View controls',
												'complyops'
											) }
										</button>
										{ canManage && (
											<button
												type="button"
												className="button button-secondary button-small"
												onClick={ () =>
													onTogglePack( pack )
												}
												disabled={
													togglingPackId === pack.id
												}
											>
												{ togglingPackId === pack.id
													? __(
															'Saving…',
															'complyops'
													  )
													: pack.active
													? __(
															'Deactivate',
															'complyops'
													  )
													: __(
															'Activate',
															'complyops'
													  ) }
											</button>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
						{ canManage && (
							<tbody className="complyops-table__add-group">
								<tr
									className="complyops-table__row--add complyops-table__row--add-ghost"
									aria-hidden="true"
								>
									<td>
										<strong>Framework pack</strong>
										<div className="complyops-muted">
											Installed pack
										</div>
									</td>
									<td>0.0.0</td>
									<td>0 / 0</td>
									<td>{ __( 'Inactive', 'complyops' ) }</td>
									<td>
										{ __( 'View controls', 'complyops' ) }
									</td>
								</tr>
								<tr className="complyops-table__row--add complyops-table__row--add-overlay">
									<td colSpan={ 5 }>
										<button
											type="button"
											className="complyops-table__add-button"
											data-complyops-tour="install-framework"
											onClick={ onInstall }
										>
											<span className="complyops-table__add-label">
												+{ ' ' }
												{ __(
													'Install New Framework',
													'complyops'
												) }
											</span>
										</button>
									</td>
								</tr>
							</tbody>
						) }
					</table>
				</div>
			</div>
		</>
	);
}

function controlNeedsScope( control ) {
	const type = String( control?.verification_type || '' ).toUpperCase();

	return (
		type === 'H' ||
		type === 'L' ||
		control?.applicability?.requires_confirmation === true
	);
}

function PackDetailView( {
	pack,
	controls,
	disclaimer,
	loading,
	error,
	filter,
	onFilterChange,
	onBack,
	onToggleControl,
	onManageScope,
	togglingControlId,
} ) {
	const filtered = controls.filter( ( control ) => {
		if ( ! filter ) {
			return true;
		}

		const haystack = `${ control.id } ${ control.title } ${
			control.category
		} ${ control.disabled_comment || '' }`.toLowerCase();
		return haystack.includes( filter.toLowerCase() );
	} );

	if ( loading && ! controls.length ) {
		return (
			<LoadingState message={ __( 'Loading controls…', 'complyops' ) } />
		);
	}

	return (
		<div className="complyops-detail-screen">
			<DetailBackLink
				onClick={ onBack }
				label={ __( 'Back to framework packs', 'complyops' ) }
			/>

			<PageHeader
				eyebrow={ __( 'Manage', 'complyops' ) }
				title={ pack?.label || __( 'Framework controls', 'complyops' ) }
				description={ __(
					'Enable or disable individual controls in this framework. Disabled controls are skipped during audits.',
					'complyops'
				) }
				meta={
					pack ? (
						<span className="complyops-muted">
							{ pack.version } · { pack.enabled_control_count } /{ ' ' }
							{ pack.control_count }{ ' ' }
							{ __( 'enabled', 'complyops' ) }
						</span>
					) : null
				}
			/>

			<div className="complyops-toolbar complyops-toolbar--wrap">
				<input
					type="search"
					className="regular-text"
					placeholder={ __( 'Search controls…', 'complyops' ) }
					value={ filter }
					onChange={ ( event ) =>
						onFilterChange( event.target.value )
					}
				/>
				<span className="complyops-toolbar__count">
					{ filtered.length } / { controls.length }{ ' ' }
					{ __( 'controls', 'complyops' ) }
				</span>
			</div>

			{ error && (
				<div
					className="complyops-alert complyops-alert--danger"
					role="alert"
				>
					{ error }
				</div>
			) }

			<PackDisclaimer text={ disclaimer } />

			<div className="complyops-panel complyops-panel--flush">
				<div className="complyops-table-wrap">
					<table className="widefat striped complyops-table">
						<thead>
							<tr>
								<th>{ __( 'ID', 'complyops' ) }</th>
								<th>{ __( 'Title', 'complyops' ) }</th>
								<th>{ __( 'Category', 'complyops' ) }</th>
								<th>{ __( 'Severity', 'complyops' ) }</th>
								<th>{ __( 'Status', 'complyops' ) }</th>
								{ canManage && (
									<th>{ __( 'Actions', 'complyops' ) }</th>
								) }
							</tr>
						</thead>
						<tbody>
							{ filtered.map( ( control ) => (
								<tr
									key={ control.id }
									className={
										control.enabled
											? ''
											: 'complyops-table__row--muted'
									}
								>
									<td>
										<code>{ control.id }</code>
									</td>
									<td>
										<strong>{ control.title }</strong>
										<div className="complyops-muted">
											{ control.description }
										</div>
										{ ! control.enabled &&
											control.disabled_comment && (
												<div className="complyops-control-disable-note">
													<strong>
														{ __(
															'Disabled:',
															'complyops'
														) }
													</strong>{ ' ' }
													{ control.disabled_comment }
												</div>
											) }
									</td>
									<td>{ control.category }</td>
									<td>
										<SeverityBadge
											severity={ control.severity }
										/>
									</td>
									<td>
										<StatusBadge
											status={
												control.enabled
													? 'PASS'
													: 'UNKNOWN'
											}
											label={
												control.enabled
													? __(
															'Enabled',
															'complyops'
													  )
													: __(
															'Disabled',
															'complyops'
													  )
											}
										/>
									</td>
									{ canManage && (
										<td className="complyops-table__actions">
											{ controlNeedsScope( control ) && (
												<button
													type="button"
													className="button button-link button-small"
													onClick={ () =>
														onManageScope( control )
													}
												>
													{ __(
														'Scope',
														'complyops'
													) }
												</button>
											) }
											<button
												type="button"
												className="button button-secondary button-small"
												onClick={ () =>
													onToggleControl( control )
												}
												disabled={
													togglingControlId ===
													control.id
												}
											>
												{ togglingControlId ===
												control.id
													? __(
															'Saving…',
															'complyops'
													  )
													: control.enabled
													? __(
															'Disable',
															'complyops'
													  )
													: __(
															'Enable',
															'complyops'
													  ) }
											</button>
										</td>
									) }
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			</div>
		</div>
	);
}

export default function ControlsPage() {
	const [ packs, setPacks ] = useState( [] );
	const [ controls, setControls ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ controlsLoading, setControlsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ controlsError, setControlsError ] = useState( null );
	const [ filter, setFilter ] = useState( '' );
	const [ installOpen, setInstallOpen ] = useState( false );
	const [ togglingPackId, setTogglingPackId ] = useState( null );
	const [ togglingControlId, setTogglingControlId ] = useState( null );
	const [ disableTarget, setDisableTarget ] = useState( null );
	const [ scopeTarget, setScopeTarget ] = useState( null );
	const [ packDisclaimer, setPackDisclaimer ] = useState( '' );
	const [ packId, setPackId ] = useState(
		() => getQueryParam( 'pack' ) || ''
	);
	const notify = useNotify();

	const loadPacks = useCallback( async () => {
		const data = await getFrameworks( true );
		setPacks( data.frameworks || [] );
		return data.frameworks || [];
	}, [] );

	const loadControls = useCallback( async ( frameworkId ) => {
		if ( ! frameworkId ) {
			return;
		}

		setControlsLoading( true );
		setControlsError( null );

		try {
			const data = await getControls( frameworkId );
			setControls( data.controls || [] );
			setPackDisclaimer( data.disclaimer || '' );
		} catch ( err ) {
			setControlsError(
				err?.message || __( 'Failed to load controls.', 'complyops' )
			);
		} finally {
			setControlsLoading( false );
		}
	}, [] );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			await loadPacks();
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to load framework packs.', 'complyops' )
			);
		} finally {
			setLoading( false );
		}
	}, [ loadPacks ] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		if ( ! packId ) {
			setControls( [] );
			setFilter( '' );
			return;
		}

		loadControls( packId );
	}, [ packId, loadControls ] );

	const openPack = ( id ) => {
		setPackId( id );
		setQueryParam( 'pack', id );
	};

	const closePack = () => {
		setPackId( '' );
		setQueryParam( 'pack', null );
	};

	const refreshPackSummary = useCallback(
		async ( frameworkId ) => {
			const items = await loadPacks();
			return items.find( ( item ) => item.id === frameworkId ) || null;
		},
		[ loadPacks ]
	);

	const handleTogglePack = async ( pack ) => {
		if ( ! canManage ) {
			return;
		}

		setTogglingPackId( pack.id );
		setError( null );

		try {
			if ( pack.active ) {
				await deactivateFramework( pack.id );
				notify.success( __( 'Framework deactivated.', 'complyops' ) );
			} else {
				await activateFramework( pack.id );
				notify.success( __( 'Framework activated.', 'complyops' ) );
			}

			await loadPacks();
		} catch ( err ) {
			notify.error(
				err?.message ||
					__( 'Failed to update framework status.', 'complyops' )
			);
		} finally {
			setTogglingPackId( null );
		}
	};

	const applyControlState = ( controlId, state ) => {
		setControls( ( current ) =>
			current.map( ( control ) =>
				control.id === controlId
					? {
							...control,
							enabled: state.enabled,
							disabled_comment: state.disabled_comment,
							disabled_at: state.disabled_at,
							disabled_by: state.disabled_by,
					  }
					: control
			)
		);
	};

	const handleEnableControl = async ( control ) => {
		setTogglingControlId( control.id );
		setControlsError( null );

		try {
			const response = await updateControl( control.id, {
				framework: packId,
				enabled: true,
			} );
			applyControlState( control.id, response.state );
			await refreshPackSummary( packId );
			notify.success( __( 'Control enabled.', 'complyops' ) );
		} catch ( err ) {
			notify.error(
				err?.message || __( 'Failed to enable control.', 'complyops' )
			);
		} finally {
			setTogglingControlId( null );
		}
	};

	const handleDisableControl = async ( comment ) => {
		if ( ! disableTarget ) {
			return;
		}

		setTogglingControlId( disableTarget.id );
		setControlsError( null );

		try {
			const response = await updateControl( disableTarget.id, {
				framework: packId,
				enabled: false,
				comment,
			} );
			applyControlState( disableTarget.id, response.state );
			await refreshPackSummary( packId );
			setDisableTarget( null );
			notify.success( __( 'Control disabled.', 'complyops' ) );
		} catch ( err ) {
			notify.error(
				err?.message || __( 'Failed to disable control.', 'complyops' )
			);
		} finally {
			setTogglingControlId( null );
		}
	};

	const handleToggleControl = ( control ) => {
		if ( control.enabled ) {
			setDisableTarget( control );
			return;
		}

		handleEnableControl( control );
	};

	const handleScopeUpdated = ( patch ) => {
		if ( ! scopeTarget?.id ) {
			return;
		}

		setControls( ( current ) =>
			current.map( ( control ) =>
				control.id === scopeTarget.id
					? { ...control, ...patch }
					: control
			)
		);
		setScopeTarget( ( current ) =>
			current ? { ...current, ...patch } : current
		);
	};

	const handleInstalled = ( result ) => {
		if ( result?.framework ) {
			openPack( result.framework );
		}

		window.location.reload();
	};

	const activePack = packs.find( ( item ) => item.id === packId ) || null;

	return (
		<div className="complyops-controls-page">
			{ packId ? (
				<PackDetailView
					pack={ activePack }
					controls={ controls }
					disclaimer={ packDisclaimer }
					loading={ controlsLoading }
					error={ controlsError }
					filter={ filter }
					onFilterChange={ setFilter }
					onBack={ closePack }
					onToggleControl={ handleToggleControl }
					onManageScope={ setScopeTarget }
					togglingControlId={ togglingControlId }
				/>
			) : (
				<PackListView
					packs={ packs }
					loading={ loading }
					error={ error }
					onRetry={ load }
					onOpenPack={ openPack }
					onTogglePack={ handleTogglePack }
					togglingPackId={ togglingPackId }
					onInstall={ () => setInstallOpen( true ) }
				/>
			) }

			{ ! packId && (
				<div className="complyops-panel complyops-panel--tint">
					<SectionHeader
						title={ __( 'About technical readiness', 'complyops' ) }
					/>
					<p>
						{ __(
							'ComplyOps verifies technical controls it can observe, records evidence, and identifies manual review. It does not replace counsel or certify legal compliance.',
							'complyops'
						) }
					</p>
				</div>
			) }

			{ canManage && (
				<>
					<InstallFrameworkModal
						isOpen={ installOpen }
						onClose={ () => setInstallOpen( false ) }
						onInstalled={ handleInstalled }
					/>
					<DisableControlDialog
						control={ disableTarget }
						isOpen={ !! disableTarget }
						onCancel={ () => setDisableTarget( null ) }
						onConfirm={ handleDisableControl }
						confirming={ !! togglingControlId && !! disableTarget }
					/>
					<Dialog
						isOpen={ !! scopeTarget }
						onClose={ () => setScopeTarget( null ) }
						title={ __(
							'Control scope and evidence',
							'complyops'
						) }
						description={
							scopeTarget
								? `${ scopeTarget.id } · ${ scopeTarget.title }`
								: ''
						}
						footer={
							<div className="complyops-dialog__footer-actions complyops-dialog__footer-actions--end">
								<button
									type="button"
									className="button button-secondary"
									onClick={ () => setScopeTarget( null ) }
								>
									{ __( 'Close', 'complyops' ) }
								</button>
							</div>
						}
					>
						<div className="complyops-dialog__body">
							<ControlWorkflowPanel
								finding={ {
									...scopeTarget,
									control_id: scopeTarget?.id,
									framework: packId,
								} }
								canManage={ canManage }
								onUpdated={ handleScopeUpdated }
							/>
						</div>
					</Dialog>
				</>
			) }
		</div>
	);
}
