import { __ } from '@wordpress/i18n';

export function BannerPreviewActions() {
	return (
		<div
			className="complyops-consent-banner__actions complyops-banner-designer__actions"
			aria-hidden="true"
		>
			<div className="complyops-consent-banner__actions-start">
				<span className="complyops-consent-btn complyops-consent-btn--link">
					{ __( 'Manage Preferences', 'complyops' ) }
				</span>
			</div>
			<div className="complyops-consent-banner__actions-end">
				<span className="complyops-consent-btn">
					{ __( 'Reject Non-Essential', 'complyops' ) }
				</span>
				<span className="complyops-consent-btn complyops-consent-btn--primary">
					{ __( 'Accept All', 'complyops' ) }
				</span>
			</div>
		</div>
	);
}

export function ReadOnlyBannerPreview( {
	headline,
	description,
	showSiteLogo,
	siteLogoUrl,
	siteName,
} ) {
	const showIcon = !! showSiteLogo && !! siteLogoUrl;

	return (
		<div className="complyops-banner-designer complyops-banner-designer--readonly">
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
							<h2
								className="complyops-banner-designer__headline"
								dangerouslySetInnerHTML={ {
									// nosemgrep: typescript.react.security.audit.react-dangerouslysetinnerhtml.react-dangerouslysetinnerhtml -- Admin preview of server-sanitized banner HTML.
									__html: headline || '',
								} }
							/>
							<div
								className="complyops-banner-designer__description complyops-consent-banner__description"
								dangerouslySetInnerHTML={ {
									// nosemgrep: typescript.react.security.audit.react-dangerouslysetinnerhtml.react-dangerouslysetinnerhtml -- Admin preview of server-sanitized banner HTML.
									__html: description || '',
								} }
							/>
						</div>
					</div>
					<BannerPreviewActions />
				</div>
			</div>
		</div>
	);
}
