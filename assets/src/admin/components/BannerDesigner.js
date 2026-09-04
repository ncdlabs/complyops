import { useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { BlockEditorProvider, RichText } from '@wordpress/block-editor';
import '@wordpress/format-library';
import { BannerPreviewActions } from './banner-preview';

const ALLOWED_FORMATS = [ 'core/bold', 'core/italic', 'core/link' ];

function editPlaceholder( label ) {
	return sprintf(
		/* translators: %s: banner element label, e.g. Title or Description */
		__( '%s - click here to edit', 'complyops' ),
		label
	);
}

const BLOCK_PLACEHOLDERS = {
	headline: editPlaceholder( __( 'Title', 'complyops' ) ),
	description: editPlaceholder( __( 'Description', 'complyops' ) ),
};

function normalizeDescriptionHtml( value ) {
	const trimmed = ( value || '' ).trim();

	if ( ! trimmed ) {
		return '';
	}

	if ( /<p[\s>]/i.test( trimmed ) ) {
		return value;
	}

	return `<p>${ trimmed }</p>`;
}

export default function BannerDesigner( {
	headline,
	description,
	showReopenButton,
	showSiteLogo,
	siteLogoUrl,
	siteName,
	canShowSiteLogo,
	onHeadlineChange,
	onDescriptionChange,
	onShowReopenChange,
	onShowSiteLogoChange,
	compact = false,
} ) {
	const showIcon = !! showSiteLogo && !! siteLogoUrl;
	const descriptionValue = useMemo(
		() => normalizeDescriptionHtml( description ),
		[ description ]
	);
	const logoHelp = canShowSiteLogo
		? __(
				'Display the site logo to the left of the banner title and description.',
				'complyops'
		  )
		: __(
				'No site logo is set for this site. Set one under Appearance in your WordPress admin to enable this option.',
				'complyops'
		  );
	const logoHelpCompact = canShowSiteLogo
		? null
		: __(
				'No site logo set — add one under Appearance to enable.',
				'complyops'
		  );
	const reopenHelp = __(
		'After a visitor accepts, rejects, or saves preferences, show a small floating Privacy settings button at the bottom-left of the site. Visitors can use it anytime to reopen cookie preferences or withdraw consent.',
		'complyops'
	);
	const reopenHelpCompact = __(
		'Floating button so visitors can reopen preferences later.',
		'complyops'
	);

	return (
		<div
			className={
				compact
					? 'complyops-banner-designer complyops-banner-designer--compact'
					: 'complyops-banner-designer'
			}
		>
			{ ! compact && (
				<p className="complyops-banner-designer__intro complyops-muted">
					{ __(
						'Edit the banner content directly in the preview below.',
						'complyops'
					) }
				</p>
			) }

			<BlockEditorProvider settings={ {} }>
				<div
					className="complyops-banner-designer__preview complyops-consent-banner"
					role="presentation"
					aria-label={ __( 'Banner preview', 'complyops' ) }
				>
					<div className="complyops-consent-banner__content">
						<div className="complyops-consent-banner__body">
							{ showIcon && (
								<img
									src={ siteLogoUrl }
									alt={ siteName || '' }
									className="complyops-consent-banner__site-logo"
									decoding="async"
								/>
							) }
							<div className="complyops-consent-banner__text">
								<RichText
									tagName="h2"
									className="complyops-banner-designer__headline"
									value={ headline || '' }
									onChange={ onHeadlineChange }
									allowedFormats={ ALLOWED_FORMATS }
									placeholder={ BLOCK_PLACEHOLDERS.headline }
									aria-label={ __(
										'Banner headline',
										'complyops'
									) }
								/>
								<RichText
									tagName="div"
									className="complyops-banner-designer__description"
									value={ descriptionValue }
									onChange={ onDescriptionChange }
									allowedFormats={ ALLOWED_FORMATS }
									multiline="p"
									placeholder={
										BLOCK_PLACEHOLDERS.description
									}
									aria-label={ __(
										'Banner description',
										'complyops'
									) }
								/>
							</div>
						</div>
						<BannerPreviewActions />
					</div>
				</div>
			</BlockEditorProvider>

			<div className="complyops-banner-designer__option">
				<input
					id="complyops-show-site-logo"
					className="complyops-banner-designer__option-input"
					type="checkbox"
					checked={ !! showSiteLogo && canShowSiteLogo }
					disabled={ ! canShowSiteLogo }
					onChange={ ( event ) =>
						onShowSiteLogoChange( event.target.checked )
					}
				/>
				<label
					className="complyops-banner-designer__option-label"
					htmlFor="complyops-show-site-logo"
				>
					{ __( 'Show Site Logo', 'complyops' ) }
				</label>
				{ ( compact ? logoHelpCompact : logoHelp ) && (
					<p className="complyops-muted complyops-banner-designer__option-help">
						{ compact ? logoHelpCompact : logoHelp }
					</p>
				) }
			</div>

			<div className="complyops-banner-designer__option">
				<input
					id="complyops-show-reopen-button"
					className="complyops-banner-designer__option-input"
					type="checkbox"
					checked={ !! showReopenButton }
					onChange={ ( event ) =>
						onShowReopenChange( event.target.checked )
					}
				/>
				<label
					className="complyops-banner-designer__option-label"
					htmlFor="complyops-show-reopen-button"
				>
					{ compact
						? __( 'Show Privacy settings button', 'complyops' )
						: __(
								'Show Privacy Settings Button After Consent',
								'complyops'
						  ) }
				</label>
				<p className="complyops-muted complyops-banner-designer__option-help">
					{ compact ? reopenHelpCompact : reopenHelp }
				</p>
			</div>
		</div>
	);
}
