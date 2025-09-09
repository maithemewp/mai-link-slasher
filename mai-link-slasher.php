<?php

/**
 * Plugin Name:     Mai Link Slasher
 * Plugin URI:      https://bizbudding.com/
 * Description:     Forces a trailing slash on interal links within the content.
 * Version:         0.1.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

namespace Mai\LinkSlasher;

use WP_HTML_Tag_Processor;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include dependencies.
require_once __DIR__ . '/vendor/autoload.php';

add_filter( 'the_content', __NAMESPACE__ . '\handle_link_slashing', 20 );
/**
 * Handles link slashing in the content.
 *
 * @since 0.1.0
 *
 * @param string $content The content.
 *
 * @return string
 */
function handle_link_slashing( $content ) {
	// Bail if no content or not the main query.
	if ( ! $content || ! is_main_query() ) {
		return $content;
	}

	// Get the local host without the www.
	$host = str_replace( 'www.', '', wp_parse_url( home_url(), PHP_URL_HOST ) );

	// Set up tag processor.
	$tags  = new WP_HTML_Tag_Processor( $content );

	// Loop through anchor link tags.
	while ( $tags->next_tag( [ 'tag_name' => 'a' ] ) ) {
		$href = $tags->get_attribute( 'href' );

		// Skip if no href.
		if ( ! $href ) {
			continue;
		}

		// Skip if not a local url.
		if ( ! str_contains( $href, $host ) ) {
			continue;
		}

		// Parse the url.
		$parts = wp_parse_url( $href );

		// Skip if no path or path has an extension.
		if ( ! isset( $parts['path'] ) || empty( $parts['path'] ) || pathinfo( $parts['path'], PATHINFO_EXTENSION ) ) {
			continue;
		}

		// Add trailing slash and rebuild the url.
		$path = trailingslashit( $parts['path'] );
		$url  = $parts['scheme'] . '://' . $parts['host'] . $path;

		// Maybe add query.
		if ( isset( $parts['query'] ) && $parts['query'] ) {
			$url .= '?' . $parts['query'];
		}

		// Maybe add anchor/fragment.
		if ( isset( $parts['fragment'] ) && $parts['fragment'] ) {
			$url .= '#' . $parts['fragment'];
		}

		// Update the href.
		$tags->set_attribute( 'href', $url );
	}

	return $tags->get_updated_html();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\updater' );
/**
 * Setup the updater.
 *
 * composer require yahnis-elsts/plugin-update-checker
 *
 * @since 0.1.0
 *
 * @uses https://github.com/YahnisElsts/plugin-update-checker/
 *
 * @return void
 */
function updater() {
	// Bail if plugin updater is not loaded.
	if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}

	// Setup the updater.
	$updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/mai-link-slasher/', __FILE__, 'mai-link-slasher' );

	// Maybe set github api token.
	if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
		$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
	}

	// Add icons for Dashboard > Updates screen.
	if ( function_exists( 'mai_get_updater_icons' ) && $icons = \mai_get_updater_icons() ) {
		$updater->addResultFilter(
			function ( $info ) use ( $icons ) {
				$info->icons = $icons;
				return $info;
			}
		);
	}
}