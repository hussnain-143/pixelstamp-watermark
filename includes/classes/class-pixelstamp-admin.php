<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin Logic Class
 */
class PixelStampWatermarkAdmin {
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_head', [$this, 'admin_menu_icon_css']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_pixelstamp_apply', [$this, 'ajax_apply_watermark']);
        add_action('wp_ajax_pixelstamp_restore', [$this, 'ajax_restore_image']);
        add_action('wp_ajax_pixelstamp_get_all_ids', [$this, 'ajax_get_all_ids']);
        add_action('wp_ajax_pixelstamp_save_settings', [$this, 'ajax_save_settings']);
        add_action('plugin_action_links_' . plugin_basename(PIXELSTAMP_WATERMARK_PATH . 'pixelstamp-watermark.php'), [$this, 'add_plugin_action_links']);
    }

    public function admin_menu_icon_css() {
        echo '<style>
            #adminmenu .toplevel_page_pixelstamp-watermark .wp-menu-image img {
                padding: 3px 0 0 0 !important;
                max-width: 18px !important;
                max-height: 18px !important;
                width: 18px !important;
                height: auto !important;
                object-fit: contain !important;
                filter: brightness(0) invert(1) opacity(0.7) !important;
                transition: all 0.2s ease;
            }
            #adminmenu .toplevel_page_pixelstamp-watermark:hover .wp-menu-image img,
            #adminmenu .toplevel_page_pixelstamp-watermark.current .wp-menu-image img,
            #adminmenu .toplevel_page_pixelstamp-watermark.wp-has-current-submenu .wp-menu-image img {
                filter: brightness(0) invert(1) opacity(1) !important;
            }
        </style>';
    }

    public function add_admin_menu() {
        $icon_url = file_exists(PIXELSTAMP_WATERMARK_PATH . 'assets/icon.svg')
            ? 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(PIXELSTAMP_WATERMARK_PATH . 'assets/icon.svg'))
            : PIXELSTAMP_WATERMARK_URL . 'assets/logo.png';

        add_menu_page(
            __('PixelStamp Watermark', 'pixelstamp-watermark'),
            __('PixelStamp', 'pixelstamp-watermark'),
            'manage_options',
            'pixelstamp-watermark',
            [$this, 'render_admin_page'],
            $icon_url,
            60
        );
    }

    public function enqueue_assets($hook) {
        if ($hook != 'toplevel_page_pixelstamp-watermark') return;

        wp_enqueue_media();
        wp_enqueue_style('pixelstamp-admin-css', PIXELSTAMP_WATERMARK_URL . 'assets/css/admin.css', [], PIXELSTAMP_WATERMARK_VERSION);
        wp_enqueue_script('pixelstamp-admin-js', PIXELSTAMP_WATERMARK_URL . 'assets/js/admin.js', ['jquery'], PIXELSTAMP_WATERMARK_VERSION, true);

        wp_localize_script('pixelstamp-admin-js', 'pixelstamp_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pixelstamp_nonce'),
            'settings' => get_option('pixelstamp_settings', $this->plugin->get_default_settings()),
            'capabilities' => [
                'ttf' => PixelStampProcessor::can_use_ttf() ? 1 : 0,
            ],
            'i18n'     => [
                'select_title' => __('Select Images to Watermark', 'pixelstamp-watermark'),
                'select_btn'   => __('Select Images', 'pixelstamp-watermark'),
                'processing'   => __('Processing', 'pixelstamp-watermark'),
                'processing_item' => __('Processing image', 'pixelstamp-watermark'),
                'success'      => __('Success', 'pixelstamp-watermark'),
                'error'        => __('Error', 'pixelstamp-watermark'),
                'failed'       => __('Failed', 'pixelstamp-watermark'),
                'complete'     => __('Complete', 'pixelstamp-watermark'),
                'completed'    => __('Completed', 'pixelstamp-watermark'),
                /* translators: 1: number of successful images, 2: total number of images */
                'process_complete_status' => __('Completed %1$d of %2$d images.', 'pixelstamp-watermark'),
                'selected'     => __(' images selected', 'pixelstamp-watermark'),
                'confirm_all'  => __('Are you sure you want to apply the watermark to EVERY image in your media library?', 'pixelstamp-watermark'),
                'auto_apply_enabled' => __('Auto-apply enabled', 'pixelstamp-watermark'),
                'auto_apply_disabled' => __('Auto-apply disabled', 'pixelstamp-watermark'),
                'applying'     => __('Applying watermark...', 'pixelstamp-watermark'),
                'applying_all' => __('Applying watermark to all images...', 'pixelstamp-watermark'),
                'restoring'    => __('Restoring originals...', 'pixelstamp-watermark'),
                'no_images'    => __('Please select at least one image.', 'pixelstamp-watermark'),
                'ttf_unavailable' => __('Server TTF rendering is unavailable, so final watermark uses a basic fallback font. Font family and some symbols may differ.', 'pixelstamp-watermark')
            ]
        ]);
    }

    public function render_admin_page() {
        $settings = get_option('pixelstamp_settings', $this->plugin->get_default_settings());
        require_once PIXELSTAMP_WATERMARK_PATH . 'includes/admin-page.php';
    }

    public function ajax_get_all_ids() {
        check_ajax_referer('pixelstamp_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Unauthorized', 'pixelstamp-watermark'));

        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/webp'],
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids'
        ]);

        wp_send_json_success($query->posts);
    }

    public function ajax_apply_watermark() {
        check_ajax_referer('pixelstamp_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Unauthorized', 'pixelstamp-watermark'));

        $attachment_ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];
        $raw_settings = isset($_POST['settings']) ? map_deep(wp_unslash($_POST['settings']), 'sanitize_text_field') : $this->plugin->get_default_settings();
        $settings = $this->sanitize_settings($raw_settings);

        update_option('pixelstamp_settings', $settings);

        $results = [];
        $success_count = 0;
        foreach ($attachment_ids as $id) {
            $ok = (bool) $this->plugin->process_image($id, $settings);
            $results[$id] = $ok;
            if ($ok) {
                $success_count++;
            }
        }

        if (empty($attachment_ids)) {
            wp_send_json_error(__('No images selected.', 'pixelstamp-watermark'));
        }

        if ($success_count === 0) {
            wp_send_json_error([
                'message' => __('Watermark could not be applied. Check that GD is enabled and the image file is writable.', 'pixelstamp-watermark'),
                'results' => $results,
            ]);
        }

        wp_send_json_success([
            'results' => $results,
            'success_count' => $success_count,
            'total' => count($attachment_ids),
        ]);
    }

    public function ajax_restore_image() {
        check_ajax_referer('pixelstamp_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Unauthorized', 'pixelstamp-watermark'));

        $attachment_ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

        $results = [];
        $success_count = 0;
        foreach ($attachment_ids as $id) {
            $ok = (bool) $this->plugin->restore_original($id);
            $results[$id] = $ok;
            if ($ok) {
                $success_count++;
            }
        }

        if (empty($attachment_ids)) {
            wp_send_json_error(__('No images selected.', 'pixelstamp-watermark'));
        }

        if ($success_count === 0) {
            wp_send_json_error([
                'message' => __('No backup found for the selected image(s).', 'pixelstamp-watermark'),
                'results' => $results,
            ]);
        }

        wp_send_json_success([
            'results' => $results,
            'success_count' => $success_count,
            'total' => count($attachment_ids),
        ]);
    }

    public function ajax_save_settings() {
        check_ajax_referer('pixelstamp_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Unauthorized', 'pixelstamp-watermark'));

        $raw_settings = isset($_POST['settings']) ? map_deep(wp_unslash($_POST['settings']), 'sanitize_text_field') : [];
        $settings = $this->sanitize_settings($raw_settings);

        update_option('pixelstamp_settings', $settings);

        wp_send_json_success(__('Settings saved automatically.', 'pixelstamp-watermark'));
    }

    private function sanitize_settings($settings) {
        $defaults = $this->plugin->get_default_settings();
        $sanitized = [];

        $sanitized['text'] = sanitize_textarea_field($settings['text'] ?? $defaults['text']);
        $sanitized['font'] = sanitize_key($settings['font'] ?? $defaults['font']);
        $sanitized['size'] = absint($settings['size'] ?? $defaults['size']);
        $sanitized['opacity'] = floatval($settings['opacity'] ?? $defaults['opacity']);
        $sanitized['color'] = sanitize_hex_color($settings['color'] ?? $defaults['color']) ?: $defaults['color'];
        $sanitized['position'] = sanitize_key($settings['position'] ?? $defaults['position']);
        $sanitized['use_box'] = !empty($settings['use_box']) ? 1 : 0;
        $sanitized['box_bg'] = sanitize_hex_color($settings['box_bg'] ?? $defaults['box_bg']) ?: $defaults['box_bg'];
        $sanitized['box_border'] = sanitize_hex_color($settings['box_border'] ?? $defaults['box_border']) ?: $defaults['box_border'];
        $sanitized['box_padding'] = absint($settings['box_padding'] ?? $defaults['box_padding']);
        $sanitized['box_radius'] = absint($settings['box_radius'] ?? $defaults['box_radius']);
        $sanitized['offset_x'] = intval($settings['offset_x'] ?? $defaults['offset_x'] ?? 0);
        $sanitized['offset_y'] = intval($settings['offset_y'] ?? $defaults['offset_y'] ?? 0);
        $sanitized['rotation'] = intval($settings['rotation'] ?? $defaults['rotation'] ?? 0);
        $sanitized['scale'] = floatval($settings['scale'] ?? $defaults['scale'] ?? 1);
        $sanitized['auto_apply'] = !empty($settings['auto_apply']) ? 1 : 0;

        return $sanitized;
    }

    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=pixelstamp-watermark') . '">' . esc_html__('Settings', 'pixelstamp-watermark') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
