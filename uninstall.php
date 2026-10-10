<?php
/**
 * Removes everything the plugin stored.
 *
 * WordPress runs this file when the plugin is deleted from the Plugins screen.
 * The plugin stores nothing yet; each option it comes to write is deleted
 * here, in the same change that adds it.
 *
 * @package EmailSignatureGenerator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
