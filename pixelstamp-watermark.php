<?php
/**
 * Plugin Name: PixelStamp Watermark
 * Description: Add customizable text watermarks to your images with auto-watermark on upload, bulk media processing, and original image restoration.
 * Version:           1.2.0
 * Requires at least: 5.0
 * Tested up to:      7.1
 * Author:            Hussnain Ahmed
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pixelstamp-watermark
 * Domain Path:       /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

define('PIXELSTAMP_WATERMARK_VERSION', '1.2.0');
define('PIXELSTAMP_WATERMARK_PATH', plugin_dir_path(__FILE__));
define('PIXELSTAMP_WATERMARK_URL', plugin_dir_url(__FILE__));

/**
 * Initialize the plugin
 */
function pixelstamp_watermark_init() {
    // Check for GD library
    if (!extension_loaded('gd')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__('PixelStamp Watermark requires the GD library to be enabled on your server. Please contact your host to enable it.', 'pixelstamp-watermark') . '</p></div>';
        });
        return;
    }

    require_once PIXELSTAMP_WATERMARK_PATH . 'includes/classes/class-pixelstamp-processor.php';
    require_once PIXELSTAMP_WATERMARK_PATH . 'includes/classes/class-pixelstamp-admin.php';
    require_once PIXELSTAMP_WATERMARK_PATH . 'includes/classes/class-pixelstamp-watermark.php';

    PixelStampWatermark::get_instance();
}
add_action('plugins_loaded', 'pixelstamp_watermark_init');
