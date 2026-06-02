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
            'text' => '© PixelStamp',
            'font' => 'inter',
            'size' => 20,
            'opacity' => 0.5,
            'color' => '#ffffff',
            'position' => 'bottom-right',
            'use_box' => 0,
            'box_bg' => '#000000',
            'box_border' => '#ffffff',
            'box_padding' => 10,
            'box_radius' => 0,
            'auto_apply' => 0
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

        $this->process_image($attachment_id, $settings);
        return $metadata;
    }

    public function process_image($attachment_id, $settings) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        $backup_dir = $this->get_backup_dir();
        $backup_path = $backup_dir . basename($file_path) . '.bak';

        if (!file_exists($backup_path)) {
            if (!is_dir($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }
            copy($file_path, $backup_path);
        } else {
            copy($backup_path, $file_path);
        }

        $applied = PixelStampProcessor::apply($file_path, $settings);
        if ($applied) {
            $this->regenerate_attachment_metadata($attachment_id, $file_path);
        }

        return $applied;
    }

    public function restore_original($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            return false;
        }

        $backup_dir = $this->get_backup_dir();
        $backup_path = $backup_dir . basename($file_path) . '.bak';

        if (file_exists($backup_path)) {
            copy($backup_path, $file_path);
            wp_delete_file($backup_path);
            $this->regenerate_attachment_metadata($attachment_id, $file_path);
            return true;
        }
        return false;
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

    private function get_backup_dir() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/pixelstamp-watermark-backups/';
        return $backup_dir;
    }
}
