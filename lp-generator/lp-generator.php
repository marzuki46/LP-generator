<?php
/**
 * Plugin Name:       LP Generator
 * Plugin URI:        https://example.com/lp-generator
 * Description:       Generates a complete TOFU/MOFU/BOFU funnel — landing page plus supporting articles — from a single offer campaign using 9Router AI, then submits new URLs to PuSH and IndexNow 10 seconds after publishing.
 * Version:           1.3.5
 * Update URI:        https://github.com/marzuki46/LP-generator
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Marzuki
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lp-generator
 * Domain Path:       /languages
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

define( 'LPG_VERSION', '1.3.5' );
define( 'LPG_FILE', __FILE__ );
define( 'LPG_PATH', plugin_dir_path( __FILE__ ) );
define( 'LPG_URL', plugin_dir_url( __FILE__ ) );
define( 'LPG_DB_VERSION', '1' );
define( 'LPG_TEXT_DOMAIN', 'lp-generator' );

require_once LPG_PATH . 'includes/class-utils.php';
require_once LPG_PATH . 'includes/class-frameworks.php';
require_once LPG_PATH . 'includes/class-prompts.php';
require_once LPG_PATH . 'includes/class-database.php';
require_once LPG_PATH . 'includes/class-content-repository.php';
require_once LPG_PATH . 'includes/class-ai-client.php';
require_once LPG_PATH . 'includes/class-amazon.php';
require_once LPG_PATH . 'includes/class-image.php';
require_once LPG_PATH . 'includes/class-indexer.php';
require_once LPG_PATH . 'includes/class-publisher.php';
require_once LPG_PATH . 'includes/class-generator.php';
require_once LPG_PATH . 'includes/class-activator.php';
require_once LPG_PATH . 'includes/class-deactivator.php';
require_once LPG_PATH . 'includes/class-updater.php';
require_once LPG_PATH . 'includes/class-plugin.php';
require_once LPG_PATH . 'admin/class-admin.php';
require_once LPG_PATH . 'admin/class-settings.php';
require_once LPG_PATH . 'admin/class-campaign-ui.php';
require_once LPG_PATH . 'admin/class-review-ui.php';
require_once LPG_PATH . 'admin/class-ajax.php';

/**
 * Activation callback.
 */
function activate(): void {
	Activator::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation callback.
 */
function deactivate(): void {
	Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Bootstrap the plugin.
 */
function init(): void {
	$plugin = new Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
