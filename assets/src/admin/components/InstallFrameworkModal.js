import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { installFrameworkPack, previewFrameworkPack } from '../api';
import { Dialog } from './Dialog';
import { Alert, SeverityBadge } from './ui';

const STORE_URL =
	window.complyopsAdmin?.storeUrl ||
	'https://ncdlabs.com/products/complyops/store/';
const ENCRYPTED_FORMAT = 'complyops-framework-pack-encrypted';

export default function InstallFrameworkModal( {
	isOpen,
	onClose,
	onInstalled,
} ) {
	const fileInputRef = useRef( null );
	const [ envelope, setEnvelope ] = useState( null );
	const [ unlockKey, setUnlockKey ] = useState( '' );
	const [ preview, setPreview ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ installing, setInstalling ] = useState( false );

	const reset = () => {
		setEnvelope( null );
		setUnlockKey( '' );
		setPreview( null );
		setError( null );
		setLoading( false );
		setInstalling( false );
		if ( fileInputRef.current ) {
			fileInputRef.current.value = '';
		}
	};

	const handleClose = () => {
		reset();
		onClose();
	};

	const handleFileChange = async ( event ) => {
		const file = event.target.files?.[ 0 ];

		if ( ! file ) {
			return;
		}

		setError( null );
		setLoading( true );
		setPreview( null );
		setEnvelope( null );
		setUnlockKey( '' );

		try {
			const text = await file.text();
			const parsed = JSON.parse( text );

			if ( ! parsed || typeof parsed !== 'object' ) {
				throw new Error( __( 'Invalid pack file.', 'complyops' ) );
			}

			setEnvelope( parsed );

			if ( parsed.pack_format !== ENCRYPTED_FORMAT ) {
				setError(
					__(
						'Compliance packs must be encrypted .complyops-pack files downloaded from your Stripe purchase.',
						'complyops'
					)
				);
				setEnvelope( null );
			}
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Could not read the compliance pack.', 'complyops' )
			);
			setEnvelope( null );
		} finally {
			setLoading( false );
		}
	};

	const handleUnlockPreview = async () => {
		if ( ! envelope || ! unlockKey.trim() ) {
			setError(
				__(
					'Enter the pack unlock key from your purchase confirmation.',
					'complyops'
				)
			);
			return;
		}

		setLoading( true );
		setError( null );
		setPreview( null );

		try {
			const data = await previewFrameworkPack(
				envelope,
				unlockKey.trim()
			);
			setPreview( data );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Could not unlock the compliance pack.', 'complyops' )
			);
		} finally {
			setLoading( false );
		}
	};

	const handleImport = async () => {
		if ( ! envelope || ! unlockKey.trim() ) {
			return;
		}

		setInstalling( true );
		setError( null );

		try {
			const result = await installFrameworkPack(
				envelope,
				unlockKey.trim()
			);
			onInstalled( result );
			handleClose();
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Could not install the framework pack.', 'complyops' )
			);
		} finally {
			setInstalling( false );
		}
	};

	const frameworkHint =
		envelope?.label_hint || envelope?.framework_hint || '';

	return (
		<Dialog
			isOpen={ isOpen }
			onClose={ handleClose }
			title={ __( 'Install New Framework', 'complyops' ) }
			description={ __(
				'Upload an encrypted ComplyOps compliance pack downloaded from your Stripe purchase and enter your unlock key.',
				'complyops'
			) }
			size="wide"
			disableEscape={ installing }
			footer={
				preview ? (
					<div className="complyops-dialog__footer-actions complyops-dialog__footer-actions--end">
						<button
							type="button"
							className="button button-secondary"
							onClick={ handleClose }
							disabled={ installing }
						>
							{ __( 'Cancel', 'complyops' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ handleImport }
							disabled={ installing }
						>
							{ installing
								? __( 'Importing…', 'complyops' )
								: preview.already_installed
								? __( 'Update framework', 'complyops' )
								: __( 'Import framework', 'complyops' ) }
						</button>
					</div>
				) : (
					<div className="complyops-dialog__footer-actions complyops-dialog__footer-actions--end">
						<button
							type="button"
							className="button button-secondary"
							onClick={ handleClose }
						>
							{ __( 'Close', 'complyops' ) }
						</button>
						{ envelope && (
							<button
								type="button"
								className="button button-primary"
								onClick={ handleUnlockPreview }
								disabled={ loading || ! unlockKey.trim() }
							>
								{ loading
									? __( 'Unlocking…', 'complyops' )
									: __( 'Unlock & preview', 'complyops' ) }
							</button>
						) }
					</div>
				)
			}
		>
			<div className="complyops-dialog__body complyops-install-framework-modal">
				{ ! preview && (
					<div className="complyops-install-framework-modal__upload">
						<p>
							{ __(
								'Compliance packs are sold separately via Stripe, encrypted, and bound to your site when imported. Download the .complyops-pack file from your order confirmation and paste your unlock key below.',
								'complyops'
							) }{ ' ' }
							<a
								href={ STORE_URL }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __(
									'Purchase compliance packs',
									'complyops'
								) }
							</a>
						</p>
						<label className="complyops-install-framework-modal__dropzone">
							<input
								ref={ fileInputRef }
								type="file"
								accept=".complyops-pack,.json,application/json"
								onChange={ handleFileChange }
								disabled={ loading }
							/>
							<span
								className="dashicons dashicons-upload"
								aria-hidden="true"
							/>
							<strong>
								{ loading
									? __( 'Reading pack…', 'complyops' )
									: __(
											'Choose a compliance pack (.complyops-pack)',
											'complyops'
									  ) }
							</strong>
							<span className="complyops-muted">
								{ __(
									'Encrypted ComplyOps framework pack',
									'complyops'
								) }
							</span>
						</label>

						{ envelope && (
							<div className="complyops-install-framework-modal__unlock">
								{ frameworkHint && (
									<p className="complyops-muted">
										{ sprintf(
											/* translators: %s: framework label */
											__(
												'Detected pack: %s',
												'complyops'
											),
											frameworkHint
										) }
									</p>
								) }
								<label htmlFor="complyops-pack-unlock-key">
									{ __( 'Pack unlock key', 'complyops' ) }
								</label>
								<input
									id="complyops-pack-unlock-key"
									type="text"
									className="regular-text"
									value={ unlockKey }
									onChange={ ( e ) =>
										setUnlockKey( e.target.value )
									}
									placeholder={ __(
										'Paste key from Stripe order confirmation',
										'complyops'
									) }
									autoComplete="off"
									spellCheck={ false }
									disabled={ loading }
								/>
								<p className="complyops-muted">
									{ __(
										'Activation binds this pack to the current WordPress site. Sharing the pack file alone is not enough.',
										'complyops'
									) }
								</p>
							</div>
						) }
					</div>
				) }

				{ error && <Alert tone="danger">{ error }</Alert> }

				{ preview && (
					<div className="complyops-install-framework-modal__preview">
						{ preview.already_installed && (
							<Alert tone="warning">
								{ __(
									'This framework is already installed. Importing will replace the existing catalog with this pack version.',
									'complyops'
								) }
							</Alert>
						) }

						{ preview.activation?.reused === false && (
							<Alert tone="success">
								{ __(
									'Pack activated for this site.',
									'complyops'
								) }
							</Alert>
						) }

						<div className="complyops-install-framework-modal__summary">
							<div>
								<span className="complyops-muted">
									{ __( 'Framework', 'complyops' ) }
								</span>
								<strong>{ preview.label }</strong>
							</div>
							<div>
								<span className="complyops-muted">
									{ __( 'Version', 'complyops' ) }
								</span>
								<strong>{ preview.version }</strong>
							</div>
							<div>
								<span className="complyops-muted">
									{ __( 'Controls', 'complyops' ) }
								</span>
								<strong>{ preview.control_count }</strong>
							</div>
						</div>

						<p className="complyops-muted">
							{ sprintf(
								/* translators: %s: framework label */
								__(
									'The following controls will be imported into ComplyOps for %s.',
									'complyops'
								),
								preview.label
							) }
						</p>

						<div className="complyops-panel complyops-panel--flush">
							<div className="complyops-table-wrap complyops-install-framework-modal__table-wrap">
								<table className="widefat striped complyops-table">
									<thead>
										<tr>
											<th>{ __( 'ID', 'complyops' ) }</th>
											<th>
												{ __( 'Title', 'complyops' ) }
											</th>
											<th>
												{ __(
													'Category',
													'complyops'
												) }
											</th>
											<th>
												{ __(
													'Severity',
													'complyops'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ preview.controls.map( ( control ) => (
											<tr key={ control.id }>
												<td>
													<code>{ control.id }</code>
												</td>
												<td>
													<strong>
														{ control.title }
													</strong>
													<div className="complyops-muted">
														{ control.description }
													</div>
												</td>
												<td>{ control.category }</td>
												<td>
													<SeverityBadge
														severity={
															control.severity
														}
													/>
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						</div>

						<button
							type="button"
							className="button button-link complyops-install-framework-modal__choose-other"
							onClick={ reset }
							disabled={ installing }
						>
							{ __( 'Choose a different pack', 'complyops' ) }
						</button>
					</div>
				) }
			</div>
		</Dialog>
	);
}
