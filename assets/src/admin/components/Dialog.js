import { useEffect, useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export function Dialog( {
	isOpen,
	onClose,
	title,
	description,
	titleId: titleIdProp,
	size = 'default',
	layer = 'base',
	closeOnBackdrop = true,
	closeLabel = __( 'Close', 'complyops' ),
	showCloseButton = true,
	disableEscape = false,
	panelClassName,
	headerAside,
	children,
	footer,
} ) {
	const generatedTitleId = useId();
	const titleId = titleIdProp || generatedTitleId;

	useEffect( () => {
		if ( ! isOpen || disableEscape ) {
			return undefined;
		}

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				onClose();
			}
		};

		document.addEventListener( 'keydown', onKeyDown );

		return () => {
			document.removeEventListener( 'keydown', onKeyDown );
		};
	}, [ disableEscape, isOpen, onClose ] );

	if ( ! isOpen ) {
		return null;
	}

	const rootClass = [
		'complyops-dialog',
		layer === 'nested' && 'complyops-dialog--nested',
		layer === 'confirm' && 'complyops-dialog--confirm',
	]
		.filter( Boolean )
		.join( ' ' );

	const panelClass = [
		'complyops-dialog__panel',
		size === 'wide' && 'complyops-dialog__panel--wide',
		size === 'preview' && 'complyops-dialog__panel--preview',
		size === 'confirm' && 'complyops-dialog__panel--confirm',
		size === 'finding' && 'complyops-dialog__panel--finding',
		panelClassName,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ rootClass } role="presentation">
			<button
				type="button"
				className="complyops-dialog__backdrop"
				aria-label={ closeLabel }
				onClick={ closeOnBackdrop ? onClose : undefined }
				tabIndex={ closeOnBackdrop ? 0 : -1 }
			/>
			<div
				className={ panelClass }
				role="dialog"
				aria-modal="true"
				aria-labelledby={ title ? titleId : undefined }
			>
				{ ( title ||
					description ||
					showCloseButton ||
					headerAside ) && (
					<header className="complyops-dialog__header">
						<div className="complyops-dialog__header-copy">
							{ title && <h2 id={ titleId }>{ title }</h2> }
							{ description && (
								<p className="complyops-muted">
									{ description }
								</p>
							) }
						</div>
						{ headerAside && (
							<div className="complyops-dialog__header-aside">
								{ headerAside }
							</div>
						) }
						{ showCloseButton && (
							<button
								type="button"
								className="complyops-dialog__close"
								aria-label={ closeLabel }
								onClick={ onClose }
							>
								<span
									className="dashicons dashicons-no-alt"
									aria-hidden="true"
								/>
							</button>
						) }
					</header>
				) }
				{ children }
				{ footer && (
					<footer className="complyops-dialog__footer">
						{ footer }
					</footer>
				) }
			</div>
		</div>
	);
}

export function ConfirmDialog( {
	isOpen,
	onCancel,
	onConfirm,
	title,
	message,
	confirmLabel = __( 'Confirm', 'complyops' ),
	cancelLabel = __( 'Cancel', 'complyops' ),
	confirming = false,
	tone = 'default',
	disableEscape = false,
} ) {
	return (
		<Dialog
			isOpen={ isOpen }
			onClose={ onCancel }
			title={ title }
			size="confirm"
			layer="confirm"
			closeLabel={ cancelLabel }
			disableEscape={ disableEscape }
			footer={
				<div className="complyops-dialog__footer-actions complyops-dialog__footer-actions--end">
					<button
						type="button"
						className="button button-secondary"
						onClick={ onCancel }
						disabled={ confirming }
					>
						{ cancelLabel }
					</button>
					<button
						type="button"
						className={ `button button-primary${
							tone === 'danger'
								? ' complyops-dialog__confirm--danger'
								: ''
						}` }
						onClick={ onConfirm }
						disabled={ confirming }
					>
						{ confirming
							? __( 'Working…', 'complyops' )
							: confirmLabel }
					</button>
				</div>
			}
		>
			<div className="complyops-dialog__body">
				<p>{ message }</p>
			</div>
		</Dialog>
	);
}

export function AlertDialog( {
	isOpen,
	onClose,
	title,
	message,
	actionLabel = __( 'OK', 'complyops' ),
} ) {
	return (
		<Dialog
			isOpen={ isOpen }
			onClose={ onClose }
			title={ title }
			size="confirm"
			layer="confirm"
			closeLabel={ actionLabel }
			footer={
				<div className="complyops-dialog__footer-actions complyops-dialog__footer-actions--end">
					<button
						type="button"
						className="button button-primary"
						onClick={ onClose }
					>
						{ actionLabel }
					</button>
				</div>
			}
		>
			<div className="complyops-dialog__body">
				<p>{ message }</p>
			</div>
		</Dialog>
	);
}
