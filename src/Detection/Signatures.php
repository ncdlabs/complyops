<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Known third-party service script and plugin signatures.
 */
final class Signatures {

	/**
	 * @return array<string, array{label: string, patterns: list<string>, plugin_slugs: list<string>}>
	 */
	public static function integrations(): array {
		return array(
			'google_analytics'   => array(
				'label'        => 'Google Analytics',
				'patterns'     => array(
					'googletagmanager.com/gtag/js',
					'google-analytics.com/analytics.js',
					'google-analytics.com/g/collect',
					'/gtag/js?id=',
				),
				'plugin_slugs' => array(
					'google-analytics-for-wordpress',
					'google-site-kit',
					'insert-headers-and-footers',
				),
			),
			'google_tag_manager' => array(
				'label'        => 'Google Tag Manager',
				'patterns'     => array(
					'googletagmanager.com/gtm.js',
					'googletagmanager.com/ns.html',
				),
				'plugin_slugs' => array(
					'duracelltomi-google-tag-manager',
					'google-site-kit',
				),
			),
			'meta_pixel'         => array(
				'label'        => 'Meta Pixel',
				'patterns'     => array(
					'connect.facebook.net',
					'facebook.com/tr',
				),
				'plugin_slugs' => array(
					'official-facebook-pixel',
					'pixel-your-site',
				),
			),
			'linkedin_insight'   => array(
				'label'        => 'LinkedIn Insight',
				'patterns'     => array(
					'snap.licdn.com',
					'px.ads.linkedin.com',
				),
				'plugin_slugs' => array(),
			),
			'hotjar'             => array(
				'label'        => 'Hotjar',
				'patterns'     => array(
					'static.hotjar.com',
					'script.hotjar.com',
				),
				'plugin_slugs' => array(),
			),
			'microsoft_clarity'  => array(
				'label'        => 'Microsoft Clarity',
				'patterns'     => array(
					'clarity.ms',
				),
				'plugin_slugs' => array(),
			),
			'youtube'            => array(
				'label'        => 'YouTube',
				'patterns'     => array(
					'youtube.com/embed',
					'youtube-nocookie.com/embed',
					'youtu.be/',
				),
				'plugin_slugs' => array(),
			),
			'vimeo'              => array(
				'label'        => 'Vimeo',
				'patterns'     => array(
					'player.vimeo.com',
					'vimeo.com/',
				),
				'plugin_slugs' => array(),
			),
			'hubspot'            => array(
				'label'        => 'HubSpot',
				'patterns'     => array(
					'js.hs-scripts.com',
					'js.hubspot.com',
				),
				'plugin_slugs' => array(
					'leadin',
					'hubspot-all-in-one-marketing',
				),
			),
			'mailchimp'          => array(
				'label'        => 'Mailchimp',
				'patterns'     => array(
					'chimpstatic.com',
					'list-manage.com',
				),
				'plugin_slugs' => array(
					'mailchimp-for-wp',
				),
			),
			'brevo'              => array(
				'label'        => 'Brevo',
				'patterns'     => array(
					'sibautomation.com',
					'sendinblue.com',
				),
				'plugin_slugs' => array(
					'mailin',
					'woocommerce-sendinblue-newsletter-subscription',
				),
			),
			'stripe'             => array(
				'label'        => 'Stripe',
				'patterns'     => array(
					'js.stripe.com',
				),
				'plugin_slugs' => array(
					'woocommerce-gateway-stripe',
					'wpforms-lite',
				),
			),
		);
	}

	/**
	 * @return array<string, array{label: string, plugin_slugs: list<string>}>
	 */
	public static function form_plugins(): array {
		return array(
			'gravity_forms'   => array(
				'label'        => 'Gravity Forms',
				'plugin_slugs' => array( 'gravityforms' ),
			),
			'wpforms'         => array(
				'label'        => 'WPForms',
				'plugin_slugs' => array( 'wpforms-lite', 'wpforms' ),
			),
			'contact_form_7'  => array(
				'label'        => 'Contact Form 7',
				'plugin_slugs' => array( 'contact-form-7' ),
			),
			'elementor_forms' => array(
				'label'        => 'Elementor',
				'plugin_slugs' => array( 'elementor', 'elementor-pro' ),
			),
		);
	}

	/**
	 * @return array<string, array{label: string, plugin_slugs: list<string>}>
	 */
	public static function consent_providers(): array {
		return array(
			'complianz'     => array(
				'label'        => 'Complianz',
				'plugin_slugs' => array( 'complianz-gdpr', 'complianz-gdpr-premium' ),
			),
			'cookiebot'     => array(
				'label'        => 'Cookiebot',
				'plugin_slugs' => array( 'cookiebot' ),
			),
			'cookie_notice' => array(
				'label'        => 'Cookie Notice',
				'plugin_slugs' => array( 'cookie-notice' ),
			),
			'complyops'     => array(
				'label'        => 'ComplyOps',
				'plugin_slugs' => array( 'complyops' ),
			),
		);
	}
}
