<?php if (!defined('ABSPATH'))
    exit; ?>

<div class="wrap pixelstamp-wrap">
    <h1><?php esc_html_e('PixelStamp Watermark – Watermarking', 'pixelstamp-watermark'); ?></h1>
    <p style="margin-bottom: 24px; color: #666; font-size: 14px;">
        <?php esc_html_e('Protect your photography with advanced watermarking. Configure your watermark settings and preview before applying to your media library.', 'pixelstamp-watermark'); ?>
    </p>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder">

            <!-- Main Content Area -->
            <div id="post-body-content">

                <!-- 1. ACTIONS & SETTINGS SECTION - Two Column Grid -->
                <div class="main-top-row">
                    <!-- Left: Actions & Selection -->
                    <div class="postbox action-selection-box">
                        <h2 class="hndle"><span><?php esc_html_e('Actions & Selection', 'pixelstamp-watermark'); ?></span></h2>
                        <div class="inside">
                            <div class="pixelstamp-main-actions">
                                <p><?php esc_html_e('Select images from your media library to process. You can apply watermarks to specific images or your entire library.', 'pixelstamp-watermark'); ?></p>
                                
                                <button type="button" id="select-images-btn" class="button button-primary button-large">
                                    <?php esc_html_e('Open Media Library', 'pixelstamp-watermark'); ?>
                                </button>
                                
                                <div id="selected-images-count">
                                    <strong><?php esc_html_e('0 images selected', 'pixelstamp-watermark'); ?></strong>
                                </div>
                                
                                <div class="action-buttons-side">
                                    <button type="button" id="apply-btn" class="button button-primary button-large" disabled>
                                        <?php esc_html_e('Apply Watermark', 'pixelstamp-watermark'); ?>
                                    </button>
                                    <button type="button" id="apply-all-btn" class="button button-secondary button-large">
                                        <?php esc_html_e('Apply to All', 'pixelstamp-watermark'); ?>
                                    </button>
                                    <button type="button" id="restore-btn" class="button button-secondary button-large" disabled>
                                        <?php esc_html_e('Restore Originals', 'pixelstamp-watermark'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Global Settings & Status -->
                    <div class="aside-column">
                        <!-- Global Settings -->
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Global Settings', 'pixelstamp-watermark'); ?></span></h2>
                            <div class="inside">
                                <div class="config-row">
                                    <label>
                                        <input type="checkbox" id="wm-auto-apply" <?php checked($settings['auto_apply'] ?? 0, 1); ?>>
                                        <strong><?php esc_html_e('Auto-apply Watermark on Upload', 'pixelstamp-watermark'); ?></strong>
                                    </label>
                                </div>
                                <p style="margin-top: 12px; font-size: 12px; color: #666;">
                                    <?php esc_html_e('Automatically apply watermarks to new images uploaded to your media library.', 'pixelstamp-watermark'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Watermark Preview -->
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Watermark Preview', 'pixelstamp-watermark'); ?></span></h2>
                            <div class="inside">
                                <div id="focused-watermark-preview-container"
                                    style="background: repeating-conic-gradient(#f0f0f1 0% 25%, transparent 0% 50%) 50% / 20px 20px; min-height: 140px; border: 2px solid #dcdcde; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; margin-bottom: 0;">
                                    <div id="focused-watermark-overlay"
                                        style="white-space: pre-wrap; text-align: center;"></div>
                                </div>
                                <p style="margin-top: 12px; font-size: 12px; color: #666; margin-bottom: 0;">
                                    <?php esc_html_e('Live preview of your watermark as you configure settings.', 'pixelstamp-watermark'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Status & Progress -->
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Status & Progress', 'pixelstamp-watermark'); ?></span></h2>
                            <div class="inside">
                                <div id="pixelstamp-progress" class="progress-wrapper" style="display: none;" aria-live="polite">
                                    <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                        <div id="pixelstamp-progress-fill" class="progress-fill"></div>
                                    </div>
                                    <p id="pixelstamp-progress-text" class="progress-text"></p>
                                </div>
                                <p id="pixelstamp-idle-status" style="font-size: 12px; color: #666; margin: 0;">
                                    <?php esc_html_e('Ready. Apply a watermark to see progress here.', 'pixelstamp-watermark'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. CONFIGURATION GRID - Responsive Multi-Column -->
                <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e('Configuration Settings', 'pixelstamp-watermark'); ?></span></h2>
                    <div class="inside">
                        <div class="config-grid">
                            <!-- Text Style Partition -->
                            <div class="config-partition">
                                <h3><?php esc_html_e('Text Style', 'pixelstamp-watermark'); ?></h3>
                                
                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Watermark Text', 'pixelstamp-watermark'); ?></strong></p>
                                    <textarea id="wm-text" placeholder="Enter your watermark text..." rows="1"><?php echo esc_textarea($settings['text'] ?? ''); ?></textarea>
                                    <small style="color: #666; margin-top: 4px; display: block;"><?php esc_html_e('The text that will appear as your watermark.', 'pixelstamp-watermark'); ?></small>
                                </div>

                                <div class="config-row config-flex">
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Font Family', 'pixelstamp-watermark'); ?></strong></p>
                                        <select id="wm-font">
                                            <option value="inter" <?php selected($settings['font'] ?? 'inter', 'inter'); ?>>Inter (Sans-serif)</option>
                                            <option value="arial" <?php selected($settings['font'] ?? 'inter', 'arial'); ?>>Arial (Sans-serif)</option>
                                            <option value="times" <?php selected($settings['font'] ?? 'inter', 'times'); ?>>Times (Serif)</option>
                                            <option value="courier" <?php selected($settings['font'] ?? 'inter', 'courier'); ?>>Courier (Monospace)</option>
                                        </select>
                                    </div>
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Font Size', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-size" min="8" max="200" step="1"
                                            placeholder="20" value="<?php echo esc_attr($settings['size'] ?? 20); ?>">
                                        <small style="color: #666; margin-top: 4px; display: block;">Pixels</small>
                                    </div>
                                </div>

                                <div class="config-row config-flex">
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Text Color', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="color" id="wm-color" class="small-color-box"
                                            value="<?php echo esc_attr($settings['color'] ?? '#ffffff'); ?>">
                                    </div>
                                    <div class="config-col">
                                        <p><strong><?php esc_html_e('Opacity', 'pixelstamp-watermark'); ?></strong></p>
                                        <input type="number" id="wm-opacity" step="0.1" min="0" max="1"
                                            value="<?php echo esc_attr($settings['opacity'] ?? 0.8); ?>">
                                        <small style="color: #666; margin-top: 4px; display: block;">0 to 1</small>
                                    </div>
                                </div>

                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Watermark Position', 'pixelstamp-watermark'); ?></strong></p>
                                    <select id="wm-position">
                                        <option value="top-left" <?php selected($settings['position'] ?? 'bottom-right', 'top-left'); ?>><?php esc_html_e('Top Left', 'pixelstamp-watermark'); ?></option>
                                        <option value="top-right" <?php selected($settings['position'] ?? 'bottom-right', 'top-right'); ?>><?php esc_html_e('Top Right', 'pixelstamp-watermark'); ?></option>
                                        <option value="center" <?php selected($settings['position'] ?? 'bottom-right', 'center'); ?>><?php esc_html_e('Center', 'pixelstamp-watermark'); ?></option>
                                        <option value="bottom-left" <?php selected($settings['position'] ?? 'bottom-right', 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'pixelstamp-watermark'); ?></option>
                                        <option value="bottom-right" <?php selected($settings['position'] ?? 'bottom-right', 'bottom-right'); ?>><?php esc_html_e('Bottom Right (Recommended)', 'pixelstamp-watermark'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <!-- Box Styling Partition -->
                            <div class="config-partition">
                                <h3><?php esc_html_e('Background Box', 'pixelstamp-watermark'); ?></h3>
                                
                                <div class="config-row">
                                    <label>
                                        <input type="checkbox" id="wm-use-box" <?php checked($settings['use_box'] ?? 0, 1); ?>>
                                        <strong><?php esc_html_e('Enable Background Box', 'pixelstamp-watermark'); ?></strong>
                                    </label>
                                    <small style="color: #666; margin-top: 8px; display: block;"><?php esc_html_e('Add a styled background behind your watermark text.', 'pixelstamp-watermark'); ?></small>
                                </div>

                                <div id="box-settings-container"
                                    style="<?php echo !empty($settings['use_box']) ? '' : 'display:none;'; ?> margin-top: 16px; padding-top: 16px; border-top: 1px solid #dcdcde;">
                                    
                                    <div class="config-row config-flex">
                                        <div class="config-col">
                                            <p><strong><?php esc_html_e('Background Color', 'pixelstamp-watermark'); ?></strong></p>
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
                                            <p><strong><?php esc_html_e('Box Padding', 'pixelstamp-watermark'); ?></strong></p>
                                            <input type="number" id="wm-box-padding" min="0" max="50" step="1"
                                                value="<?php echo esc_attr($settings['box_padding'] ?? 10); ?>">
                                            <small style="color: #666; margin-top: 4px; display: block;">Pixels</small>
                                        </div>
                                        <div class="config-col">
                                            <p><strong><?php esc_html_e('Corner Radius', 'pixelstamp-watermark'); ?></strong></p>
                                            <input type="number" id="wm-box-radius" min="0" max="100" step="1"
                                                value="<?php echo esc_attr($settings['box_radius'] ?? 0); ?>">
                                            <small style="color: #666; margin-top: 4px; display: block;">Pixels (0 = Sharp)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Spacing & Offset Partition -->
                            <div class="config-partition">
                                <h3><?php esc_html_e('Spacing & Offset', 'pixelstamp-watermark'); ?></h3>
                                
                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Horizontal Offset', 'pixelstamp-watermark'); ?></strong></p>
                                    <input type="number" id="wm-offset-x" min="-500" max="500" step="1"
                                        placeholder="0" value="<?php echo esc_attr($settings['offset_x'] ?? 0); ?>">
                                    <small style="color: #666; margin-top: 4px; display: block;">Pixels from position</small>
                                </div>

                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Vertical Offset', 'pixelstamp-watermark'); ?></strong></p>
                                    <input type="number" id="wm-offset-y" min="-500" max="500" step="1"
                                        placeholder="0" value="<?php echo esc_attr($settings['offset_y'] ?? 0); ?>">
                                    <small style="color: #666; margin-top: 4px; display: block;">Pixels from position</small>
                                </div>

                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Rotation (Degrees)', 'pixelstamp-watermark'); ?></strong></p>
                                    <input type="number" id="wm-rotation" min="-360" max="360" step="1"
                                        placeholder="0" value="<?php echo esc_attr($settings['rotation'] ?? 0); ?>">
                                    <small style="color: #666; margin-top: 4px; display: block;">0 to 360 degrees</small>
                                </div>

                                <div class="config-row">
                                    <p><strong><?php esc_html_e('Scale', 'pixelstamp-watermark'); ?></strong></p>
                                    <input type="number" id="wm-scale" min="0.5" max="3" step="0.1"
                                        placeholder="1" value="<?php echo esc_attr($settings['scale'] ?? 1); ?>">
                                    <small style="color: #666; margin-top: 4px; display: block;">Multiplier (0.5 to 3)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. LIVE PREVIEW - Full Width Comparison -->
                <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e('Live Preview', 'pixelstamp-watermark'); ?></span></h2>
                    <div class="inside">
                        <p style="margin: 0 0 16px 0; color: #666; font-size: 13px;">
                            <?php esc_html_e('Side-by-side comparison of your image before and after watermarking.', 'pixelstamp-watermark'); ?>
                        </p>
                        
                        <div class="preview-container main-preview">
                            <div class="preview-col" id="preview-col-before">
                                <div class="preview-placeholder" id="preview-placeholder">
                                    <?php esc_html_e('Original Image', 'pixelstamp-watermark'); ?>
                                </div>
                                <img src="" id="preview-img-before" class="preview-img" style="display: none;" alt="Before">
                                <div class="preview-label label-before"><?php esc_html_e('BEFORE', 'pixelstamp-watermark'); ?></div>
                            </div>

                            <div class="preview-col" id="preview-col-after">
                                <div class="preview-placeholder" id="preview-placeholder-after">
                                    <?php esc_html_e('Watermarked Preview', 'pixelstamp-watermark'); ?>
                                </div>
                                <div class="preview-image-wrapper" style="position: relative; display: inline-block; max-width: 100%; max-height: 100%;">
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