<?php
if (!defined('ABSPATH')) exit;

/**
 * Main Orchestrator Class
 */
class PixelStampWatermark {
    private static $instance = null;
    /** @var bool Prevents double watermarking when regenerating thumbnails. */
    private static $skip_auto_apply = false;
    public $admin;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();

        if (is_admin()) {
            $this->admin = new PixelStampWatermarkAdmin($this);
        }
    }

    private function load_dependencies() {
        // Dependencies are already loaded in main file
    }

    private function init_hooks() {
        add_filter('wp_generate_attachment_metadata', [$this, 'auto_apply_on_upload'], 10, 2);
    }



    public function get_default_settings() {
        return [
            'text'        => '© PixelStamp',
            'font'        => 'inter',
            'size'        => 20,
            'opacity'     => 0.8,
            'color'       => '#ffffff',
            'position'    => 'bottom-right',
            'use_box'     => 0,
            'box_bg'      => '#000000',
            'box_border'  => '#ffffff',
            'box_padding' => 10,
            'box_radius'  => 0,
            'offset_x'    => 0,
            'offset_y'    => 0,
            'rotation'    => 0,
            'scale'       => 1.0,
            'auto_apply'  => 0,
        ];
    }

    public function auto_apply_on_upload($metadata, $attachment_id) {
        if (self::$skip_auto_apply) {
            return $metadata;
        }

        $settings = get_option('pixelstamp_settings', $this->get_default_settings());
        if (empty($settings['auto_apply'])) {
            return $metadata;
        }

        $applied = $this->process_image($attachment_id, $settings);
        if ($applied) {
            $refreshed_meta = wp_get_attachment_metadata($attachment_id);
            if (!empty($refreshed_meta)) {
                return $refreshed_meta;
            }
        }

        return $metadata;
    }

    public function process_image($attachment_id, $settings, $stamp = null) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        $backup_path = $this->get_backup_path($attachment_id, $file_path);

        if (!file_exists($backup_path)) {
            copy($file_path, $backup_path);
        } else {
            // Restore from clean original backup before applying new watermark settings
            copy($backup_path, $file_path);
        }

        $applied = PixelStampProcessor::apply($file_path, $settings, $stamp);
        if ($applied) {
            update_post_meta($attachment_id, '_pixelstamp_watermarked', current_time('mysql'));
            $this->regenerate_attachment_metadata($attachment_id, $file_path);
        }

        return $applied;
    }

    public function restore_original($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            return false;
        }

        $backup_path = $this->get_existing_backup_path($attachment_id, $file_path);

        if ($backup_path && file_exists($backup_path)) {
            copy($backup_path, $file_path);
            wp_delete_file($backup_path);
            delete_post_meta($attachment_id, '_pixelstamp_watermarked');
            $this->regenerate_attachment_metadata($attachment_id, $file_path);
            return true;
        }
        return false;
    }

    public function has_backup($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            return false;
        }
        $backup_path = $this->get_existing_backup_path($attachment_id, $file_path);
        return ($backup_path && file_exists($backup_path));
    }

    public function get_existing_backup_path($attachment_id, $file_path) {
        // First check collision-safe path
        $safe_path = $this->get_backup_path($attachment_id, $file_path, false);
        if (file_exists($safe_path)) {
            return $safe_path;
        }

        // Backward compatibility: check legacy filename path
        $legacy_path = $this->get_backup_dir(false) . basename($file_path) . '.bak';
        if (file_exists($legacy_path)) {
            return $legacy_path;
        }

        return false;
    }

    public function get_backup_path($attachment_id, $file_path, $create_dir = true) {
        $backup_dir = $this->get_backup_dir($create_dir);
        $basename = sanitize_file_name(basename($file_path));
        $hash = substr(md5($file_path), 0, 8);
        return $backup_dir . intval($attachment_id) . '_' . $hash . '_' . $basename . '.bak';
    }

    private function regenerate_attachment_metadata($attachment_id, $file_path) {
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        self::$skip_auto_apply = true;
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        self::$skip_auto_apply = false;

        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }

    private function get_backup_dir($create = true) {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/pixelstamp-watermark-backups/';

        if ($create && !is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
            // Protect backup files from direct web access
            if (!file_exists($backup_dir . '.htaccess')) {
                @file_put_contents($backup_dir . '.htaccess', "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
            }
            if (!file_exists($backup_dir . 'index.html')) {
                @file_put_contents($backup_dir . 'index.html', '');
            }
        }

        return $backup_dir;
    }
}
