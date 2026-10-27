<?php if (!defined('ABSPATH'))
    exit;

$system_status = PixelStampProcessor::get_system_status();
$ttf_ready = (!empty($system_status['gd_loaded']) && !empty($system_status['freetype_support'])) || (!empty($system_status['ttf_functions']) && !empty($system_status['ttf_probe_ok']));
?>

<div class="wrap pixelstamp-wrap">
    <div class="pixelstamp-header-bar">
        <div>
            <h1><?php esc_html_e('PixelStamp Watermark', 'pixelstamp-watermark'); ?></h1>
            <p class="pixelstamp-subtitle">
                <?php esc_html_e('Configure your watermark, preview in real time, and protect your Media Library images.', 'pixelstamp-watermark'); ?>
            </p>
        </div>
        <div class="pixelstamp-sys-pills">
            <span class="sys-pill <?php echo !empty($system_status['gd_loaded']) ? 'sys-ok' : 'sys-err'; ?>">
                GD: <?php echo !empty($system_status['gd_loaded']) ? esc_html__('Loaded', 'pixelstamp-watermark') : esc_html__('Missing', 'pixelstamp-watermark'); ?>
            </span>
            <span class="sys-pill <?php echo !empty($system_status['freetype_support']) ? 'sys-ok' : 'sys-err'; ?>">
                FreeType: <?php echo !empty($system_status['freetype_support']) ? esc_html__('Enabled', 'pixelstamp-watermark') : esc_html__('Disabled', 'pixelstamp-watermark'); ?>
            </span>
        </div>
    </div>

    <!-- STICKY ACTION & PROGRESS TOOLBAR (Always visible while scrolling) -->
    <div class="pixelstamp-sticky-bar">
        <div class="sticky-bar-main">
            <div class="sticky-bar-left">
                <button type="button" id="select-images-btn" class="button button-primary pixelstamp-select-trigger">
                    <?php esc_html_e('Select Images', 'pixelstamp-watermark'); ?>
                </button>
                <div id="selected-images-count">
                    <strong><?php esc_html_e('0 images selected', 'pixelstamp-watermark'); ?></strong>
                </div>
                <label class="auto-apply-toggle" title="<?php esc_attr_e('Automatically apply watermarks to new images uploaded to your media library.', 'pixelstamp-watermark'); ?>">
                    <input type="checkbox" id="wm-auto-apply" <?php checked($settings['auto_apply'] ?? 0, 1); ?>>
                    <span><?php esc_html_e('Auto-apply on Upload', 'pixelstamp-watermark'); ?></span>
                </label>
            </div>

            <div class="sticky-bar-actions">
                <button type="button" id="apply-btn" class="button button-primary pixelstamp-apply-trigger" disabled>
                    <?php esc_html_e('Apply Watermark', 'pixelstamp-watermark'); ?>
                </button>
                <button type="button" id="apply-all-btn" class="button button-secondary pixelstamp-apply-all-trigger">
                    <?php esc_html_e('Apply to All', 'pixelstamp-watermark'); ?>
                </button>
                <button type="button" id="restore-btn" class="button button-secondary pixelstamp-restore-trigger" disabled>
                    <?php esc_html_e('Restore Originals', 'pixelstamp-watermark'); ?>
                </button>
            </div>
        </div>

        <!-- Inline Progress Bar (Only shown during active processing) -->
        <div id="pixelstamp-progress" class="progress-wrapper" style="display: none;" aria-live="polite">
            <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div id="pixelstamp-progress-fill" class="progress-fill"></div>
            </div>
            <p id="pixelstamp-progress-text" class="progress-text"></p>
        </div>
        <p id="pixelstamp-idle-status" class="hidden" style="display: none;"></p>
    </div>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">

                <!-- 1. COMPACT 3-COLUMN CONFIGURATION & BADGE PREVIEW GRID -->
                <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e('Configuration Settings', 'pixelstamp-watermark'); ?></span></h2>
                    <div class="inside">
                        <div class="config-grid">
                            <!-- Column 1: Text Style -->
                            <div class="config-partition">
                                <h3><?php esc_html_e('Text Style', 'pixelstamp-watermark'); ?></h3>

                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Watermark Text', 'pixelstamp-watermark'); ?></strong></p>
                                    <textarea id="wm-text" placeholder="Enter your watermark text..." rows="2"><?php echo esc_textarea($settings['text'] ?? ''); ?></textarea>
                                </div>

                                <div class="config-row config-flex">
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Font Family', 'pixelstamp-watermark'); ?></strong></p>
                                        <select id="wm-font">
                                            <option value="inter" <?php selected($settings['font'] ?? 'inter', 'inter'); ?>>Inter (Sans)</option>
                                            <option value="arial" <?php selected($settings['font'] ?? 'inter', 'arial'); ?>>Arial (Sans)</option>
                                            <option value="times" <?php selected($settings['font'] ?? 'inter', 'times'); ?>>Times (Serif)</option>
                                            <option value="courier" <?php selected($settings['font'] ?? 'inter', 'courier'); ?>>Courier (Mono)</option>
                                        </select>
                                    </div>
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Font Size (px)', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-size" min="8" max="200" step="1"
                                            placeholder="20" value="<?php echo esc_attr($settings['size'] ?? 20); ?>">
                                    </div>
                                </div>

                                <div class="config-row config-flex">
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Text Color', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="color" id="wm-color" class="small-color-box"
                                            value="<?php echo esc_attr($settings['color'] ?? '#ffffff'); ?>">
                                    </div>
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Opacity (0–1)', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-opacity" step="0.1" min="0" max="1"
                                            value="<?php echo esc_attr($settings['opacity'] ?? 0.8); ?>">
                                    </div>
                                </div>

                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Position', 'pixelstamp-watermark'); ?></strong></p>
                                    <select id="wm-position">
                                        <option value="top-left" <?php selected($settings['position'] ?? 'bottom-right', 'top-left'); ?>><?php esc_html_e('Top Left', 'pixelstamp-watermark'); ?></option>
                                        <option value="top-right" <?php selected($settings['position'] ?? 'bottom-right', 'top-right'); ?>><?php esc_html_e('Top Right', 'pixelstamp-watermark'); ?></option>
                                        <option value="center" <?php selected($settings['position'] ?? 'bottom-right', 'center'); ?>><?php esc_html_e('Center', 'pixelstamp-watermark'); ?></option>
                                        <option value="bottom-left" <?php selected($settings['position'] ?? 'bottom-right', 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'pixelstamp-watermark'); ?></option>
                                        <option value="bottom-right" <?php selected($settings['position'] ?? 'bottom-right', 'bottom-right'); ?>><?php esc_html_e('Bottom Right (Recommended)', 'pixelstamp-watermark'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <!-- Column 2: Background Box & Spacing -->
                            <div class="config-partition">
                                <h3><?php esc_html_e('Box & Spacing', 'pixelstamp-watermark'); ?></h3>

                                <div class="config-row">
                                    <label>
                                        <input type="checkbox" id="wm-use-box" <?php checked($settings['use_box'] ?? 0, 1); ?>>
                                        <strong><?php esc_html_e('Enable Background Box', 'pixelstamp-watermark'); ?></strong>
                                    </label>
                                </div>

                                <div id="box-settings-container"
                                    style="<?php echo !empty($settings['use_box']) ? '' : 'display:none;'; ?> margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid #dcdcde;">
                                    <div class="config-row config-flex">
                                        <div class="config-col">
                                            <p><strong><?php esc_html_e('Box Color', 'pixelstamp-watermark'); ?></strong></p>
                                            <input type="color" id="wm-box-bg" class="small-color-box"
                                                value="<?php echo esc_attr($settings['box_bg'] ?? '#000000'); ?>">
                                        </div>
                                        <div class="config-col">
                                            <p><strong><?php esc_html_e('Border Color', 'pixelstamp-watermark'); ?></strong></p>
                                            <input type="color" id="wm-box-border" class="small-color-box"
                                                value="<?php echo esc_attr($settings['box_border'] ?? '#ffffff'); ?>">
                                        </div>
                                    </div>

                                    <div class="config-row config-flex">
                                        <div class="config-col">
                                            <p><strong><?php esc_html_e('Padding (px)', 'pixelstamp-watermark'); ?></strong></p>
                                            <input type="number" id="wm-box-padding" min="0" max="50" step="1"
                                                value="<?php echo esc_attr($settings['box_padding'] ?? 10); ?>">
                                        </div>
                                        <div class="config-col">
                                            <p><strong><?php esc_html_e('Radius (px)', 'pixelstamp-watermark'); ?></strong></p>
                                            <input type="number" id="wm-box-radius" min="0" max="100" step="1"
                                                value="<?php echo esc_attr($settings['box_radius'] ?? 0); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="config-row config-flex">
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Offset X (px)', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-offset-x" min="-500" max="500" step="1"
                                            placeholder="0" value="<?php echo esc_attr($settings['offset_x'] ?? 0); ?>">
                                    </div>
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Offset Y (px)', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-offset-y" min="-500" max="500" step="1"
                                            placeholder="0" value="<?php echo esc_attr($settings['offset_y'] ?? 0); ?>">
                                    </div>
                                </div>

                                <div class="config-row config-flex">
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Rotation (°)', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-rotation" min="-360" max="360" step="1"
                                            placeholder="0" value="<?php echo esc_attr($settings['rotation'] ?? 0); ?>">
                                    </div>
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Scale (0.5–3x)', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-scale" min="0.5" max="3" step="0.1"
                                            placeholder="1" value="<?php echo esc_attr($settings['scale'] ?? 1); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Column 3: Live Badge Preview & Quick Actions -->
                            <div class="config-partition preview-partition">
                                <h3><?php esc_html_e('Badge Preview', 'pixelstamp-watermark'); ?></h3>

                                <div id="focused-watermark-preview-container" class="focused-preview-box">
                                    <div id="focused-watermark-overlay" style="white-space: pre-wrap; text-align: center;"></div>
                                </div>
                                <p class="preview-caption">
                                    <?php esc_html_e('Live watermark style preview. Select an image to compare before and after below.', 'pixelstamp-watermark'); ?>
                                </p>

                                <div class="quick-config-actions">
                                    <button type="button" class="button button-secondary pixelstamp-select-trigger">
                                        <?php esc_html_e('Select Images', 'pixelstamp-watermark'); ?>
                                    </button>
                                    <button type="button" class="button button-primary pixelstamp-apply-trigger" disabled>
                                        <?php esc_html_e('Apply Watermark', 'pixelstamp-watermark'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. LIVE IMAGE COMPARISON (With inline action buttons so no scrolling up is needed) -->
                <div class="postbox">
                    <div class="hndle preview-header-bar">
                        <span><?php esc_html_e('Live Image Preview (Before & After)', 'pixelstamp-watermark'); ?></span>
                        <div class="preview-header-actions">
                            <button type="button" class="button button-small pixelstamp-select-trigger">
                                <?php esc_html_e('Change Image', 'pixelstamp-watermark'); ?>
                            </button>
                            <button type="button" class="button button-primary button-small pixelstamp-apply-trigger" disabled>
                                <?php esc_html_e('Apply Watermark', 'pixelstamp-watermark'); ?>
                            </button>
                            <button type="button" class="button button-small pixelstamp-restore-trigger" disabled>
                                <?php esc_html_e('Restore Original', 'pixelstamp-watermark'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="inside">
                        <div class="preview-container main-preview">
                            <div class="preview-col" id="preview-col-before">
                                <div class="preview-placeholder pixelstamp-select-trigger" id="preview-placeholder" title="<?php esc_attr_e('Click to select images from Media Library', 'pixelstamp-watermark'); ?>">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span><?php esc_html_e('Click to select an image for preview', 'pixelstamp-watermark'); ?></span>
                                </div>
                                <div class="preview-image-wrapper">
                                    <img src="" id="preview-img-before" class="preview-img" style="display: none;" alt="Before">
                                </div>
                                <div class="preview-label label-before"><?php esc_html_e('BEFORE', 'pixelstamp-watermark'); ?></div>
                            </div>

                            <div class="preview-col" id="preview-col-after">
                                <div class="preview-placeholder pixelstamp-select-trigger" id="preview-placeholder-after" title="<?php esc_attr_e('Click to select images from Media Library', 'pixelstamp-watermark'); ?>">
                                    <span class="dashicons dashicons-format-gallery"></span>
                                    <span><?php esc_html_e('Watermarked Preview', 'pixelstamp-watermark'); ?></span>
                                </div>
                                <div class="preview-image-wrapper">
                                    <img src="" id="preview-img-after" class="preview-img" style="display: none;" alt="After">
                                    <div id="watermark-preview-overlay" class="watermark-overlay"></div>
                                </div>
                                <div class="preview-label label-after"><?php esc_html_e('AFTER', 'pixelstamp-watermark'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="pixelstamp-toast" role="status" aria-live="polite"></div>
