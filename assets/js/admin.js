jQuery(document).ready(function($) {
    let mediaUploader;
    let selectedIds = [];
    const i18n = pixelstamp_vars.i18n;
    const capabilities = pixelstamp_vars.capabilities || {};
    const ttfAvailable = capabilities.ttf === 1 || capabilities.ttf === '1';
    let fallbackNoticeShown = false;

    // Elements
    const $previewPlaceholder = $('#preview-placeholder');
    const $previewPlaceholderAfter = $('#preview-placeholder-after');
    const $previewImgBefore = $('#preview-img-before');
    const $previewImgAfter = $('#preview-img-after');
    const $watermarkOverlay = $('#watermark-preview-overlay');

    // Automatically update preview on image load (handles network/render latency)
    $previewImgAfter.on('load', function() {
        updateLivePreview();
    });


    // Media Uploader
    $('#select-images-btn').on('click', function(e) {
        e.preventDefault();
        if (mediaUploader) { mediaUploader.open(); return; }

        mediaUploader = wp.media({
            title: i18n.select_title,
            button: { text: i18n.select_btn },
            multiple: true
        });

        mediaUploader.on('select', function() {
            const selection = mediaUploader.state().get('selection');
            const attachments = selection.toJSON();
            
            selectedIds = attachments.map(a => a.id);
            if (selectedIds.length > 0) {
                $('#selected-images-count').text(selectedIds.length + i18n.selected);
                const firstImage = attachments[0];
                updatePreviewImage(firstImage.url);
                $('#apply-btn, #restore-btn').prop('disabled', false);
            } else {
                resetPreview();
                $('#apply-btn, #restore-btn').prop('disabled', true);
            }
        });

        mediaUploader.open();
    });

    function updatePreviewImage(url) {
        $previewPlaceholder.hide();
        $previewPlaceholderAfter.hide();
        $previewImgBefore.attr('src', url).show();
        $previewImgAfter.attr('src', url).show();
        
        // Trigger live preview update
        setTimeout(updateLivePreview, 100);
    }

    function resetPreview() {
        $previewPlaceholder.show();
        $previewPlaceholderAfter.show();
        $previewImgBefore.hide().attr('src', '');
        $previewImgAfter.hide().attr('src', '');
        $watermarkOverlay.hide();
        $('#selected-images-count').text('0 images selected');
    }

    // Live Preview Logic
    const fontMap = {
        'inter': "'Inter', sans-serif",
        'arial': 'Arial, sans-serif',
        'times': "'Times New Roman', serif",
        'courier': "'Courier New', monospace"
    };

    function normalizeTextForFallback(text) {
        if (!text) return 'PixelStamp';
        let normalized = String(text)
            .replaceAll('©', '(c)')
            .replaceAll('®', '(R)')
            .replaceAll('™', '(TM)');
        normalized = normalized.replace(/[^\x20-\x7E\n]/g, '');
        return normalized.trim() !== '' ? normalized : 'PixelStamp';
    }

    function updateLivePreview() {
        const rawText = $('#wm-text').val();
        const text = ttfAvailable ? rawText : normalizeTextForFallback(rawText);
        const font = $('#wm-font').val();
        const sizePercent = parseInt($('#wm-size').val()) || 20;
        const opacity = $('#wm-opacity').val();
        const color = $('#wm-color').val();
        const position = $('#wm-position').val();
        const useBox = $('#wm-use-box').is(':checked');
        const boxBg = $('#wm-box-bg').val();
        const boxBorder = $('#wm-box-border').val();
        const boxPadding = parseInt($('#wm-box-padding').val()) || 10;
        const boxRadius = parseInt($('#wm-box-radius').val()) || 0;
        const offsetX = parseInt($('#wm-offset-x').val()) || 0;
        const offsetY = parseInt($('#wm-offset-y').val()) || 0;
        const rotation = parseInt($('#wm-rotation').val()) || 0;
        const scale = parseFloat($('#wm-scale').val()) || 1;

        // Show/hide box settings
        if (useBox) {
            $('#box-settings-container').slideDown();
        } else {
            $('#box-settings-container').slideUp();
        }

        // Calculate shared styles
        const styleObj = {
            'font-family': ttfAvailable ? (fontMap[font] || 'sans-serif') : "'Courier New', monospace",
            'color': color,
            'opacity': opacity,
            'background-color': useBox ? hexToRgba(boxBg, opacity) : 'transparent',
            'border': useBox ? '1px solid ' + boxBorder : 'none'
        };

        if (!ttfAvailable && !fallbackNoticeShown) {
            fallbackNoticeShown = true;
            showToast(i18n.ttf_unavailable, 'info');
        }

        // Update dedicated watermark preview (always runs)
        const $focusedOverlay = $('#focused-watermark-overlay');
        $focusedOverlay.text(text);
        const focusedWidth = $('#focused-watermark-preview-container').width() || 300;
        const focusedFontSize = (focusedWidth * sizePercent) / 400 * scale;
        const focusedPadding = (focusedWidth / 1000) * boxPadding * scale;
        const focusedRadius = (focusedWidth / 1000) * boxRadius * scale;

        $focusedOverlay.css({
            ...styleObj,
            'font-size': Math.max(focusedFontSize, 8) + 'px',
            'padding': useBox ? focusedPadding + 'px' : '0',
            'border-radius': useBox ? focusedRadius + 'px' : '0',
            'transform': `rotate(${rotation}deg)`
        });

        // Early return for main preview if no image is visible
        if (!$previewImgAfter.is(':visible')) {
            $watermarkOverlay.hide();
            return;
        }

        // Update main preview
        $watermarkOverlay.show().text(text);
        const displayedWidth = $previewImgAfter.width();
        const previewFontSize = (displayedWidth * sizePercent) / 400 * scale;
        const previewPadding = (displayedWidth / 1000) * boxPadding * scale;
        const previewRadius = (displayedWidth / 1000) * boxRadius * scale;

        // Base styles without transform (will be added separately)
        $watermarkOverlay.css({
            ...styleObj,
            'font-size': Math.max(previewFontSize, 8) + 'px',
            'top': 'auto', 'bottom': 'auto', 'left': 'auto', 'right': 'auto',
            'padding': useBox ? previewPadding + 'px' : '0',
            'border-radius': useBox ? previewRadius + 'px' : '0'
        });

        // Position logic with offset and transform
        const previewMargin = displayedWidth * 0.02;
        const previewOffsetX = (displayedWidth / 400) * offsetX;
        const previewOffsetY = (displayedWidth / 400) * offsetY;
        let transformStr = `rotate(${rotation}deg)`;
        
        switch (position) {
            case 'top-left': 
                $watermarkOverlay.css({ 
                    'top': (previewMargin + previewOffsetY) + 'px', 
                    'left': (previewMargin + previewOffsetX) + 'px',
                    'transform': transformStr
                }); 
                break;
            case 'top-right': 
                $watermarkOverlay.css({ 
                    'top': (previewMargin + previewOffsetY) + 'px', 
                    'right': (previewMargin + previewOffsetX) + 'px',
                    'transform': transformStr
                }); 
                break;
            case 'bottom-left': 
                $watermarkOverlay.css({ 
                    'bottom': (previewMargin + previewOffsetY) + 'px', 
                    'left': (previewMargin + previewOffsetX) + 'px',
                    'transform': transformStr
                }); 
                break;
            case 'bottom-right': 
                $watermarkOverlay.css({ 
                    'bottom': (previewMargin + previewOffsetY) + 'px', 
                    'right': (previewMargin + previewOffsetX) + 'px',
                    'transform': transformStr
                }); 
                break;
            case 'center': 
                $watermarkOverlay.css({ 
                    'top': '50%', 'left': '50%', 
                    'transform': `translate(calc(-50% + ${previewOffsetX}px), calc(-50% + ${previewOffsetY}px)) rotate(${rotation}deg)`
                }); 
                break;
        }
    }

    function hexToRgba(hex, alpha) {
        let r = 0, g = 0, b = 0;
        if (hex.length === 4) {
            r = parseInt(hex[1] + hex[1], 16);
            g = parseInt(hex[2] + hex[2], 16);
            b = parseInt(hex[3] + hex[3], 16);
        } else if (hex.length === 7) {
            r = parseInt(hex.substring(1, 3), 16);
            g = parseInt(hex.substring(3, 5), 16);
            b = parseInt(hex.substring(5, 7), 16);
        }
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    // Settings listeners
    $('#wm-text, #wm-font, #wm-size, #wm-opacity, #wm-color, #wm-position, #wm-use-box, #wm-box-bg, #wm-box-border, #wm-box-padding, #wm-box-radius, #wm-offset-x, #wm-offset-y, #wm-rotation, #wm-scale').on('input change', function() {
        updateLivePreview();
        saveSettings();
    });

    $('#wm-auto-apply').on('change', function() {
        const enabled = $(this).is(':checked');
        updateLivePreview();
        saveSettings();
        showToast(enabled ? i18n.auto_apply_enabled : i18n.auto_apply_disabled);
    });

    $(window).on('resize', updateLivePreview);


    // Save Global Settings
    function saveSettings() {
        const settings = {
            text: $('#wm-text').val(),
            font: $('#wm-font').val(),
            size: $('#wm-size').val(),
            opacity: $('#wm-opacity').val(),
            color: $('#wm-color').val(),
            position: $('#wm-position').val(),
            use_box: $('#wm-use-box').is(':checked') ? 1 : 0,
            box_bg: $('#wm-box-bg').val(),
            box_border: $('#wm-box-border').val(),
            box_padding: $('#wm-box-padding').val(),
            box_radius: $('#wm-box-radius').val(),
            offset_x: $('#wm-offset-x').val(),
            offset_y: $('#wm-offset-y').val(),
            rotation: $('#wm-rotation').val(),
            scale: $('#wm-scale').val(),
            auto_apply: $('#wm-auto-apply').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: pixelstamp_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'pixelstamp_save_settings',
                nonce: pixelstamp_vars.nonce,
                settings: settings
            },
            success: function(response) {
                // Silent save for better UX
            }
        });
    }


    // Apply Watermark
    $('#apply-btn').on('click', function() {
        processBatch(selectedIds, 'pixelstamp_apply');
    });

    // Apply to All
    $('#apply-all-btn').on('click', function() {
        if (!confirm(i18n.confirm_all)) return;
        $(this).prop('disabled', true);
        $.ajax({
            url: pixelstamp_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'pixelstamp_get_all_ids',
                nonce: pixelstamp_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    processBatch(response.data, 'pixelstamp_apply');
                } else {
                    alert('Error fetching media: ' + response.data);
                }
                $('#apply-all-btn').prop('disabled', false);
            }
        });
    });

    // Restore Original
    $('#restore-btn').on('click', function() {
        processBatch(selectedIds, 'pixelstamp_restore');
    });

    function formatCompleteMessage(success, total) {
        return i18n.process_complete_status
            .replace('%1$d', success)
            .replace('%2$d', total);
    }

    function getResultForId(response, id) {
        if (!response || !response.success) {
            return false;
        }
        const data = response.data;
        if (data && data.results && Object.prototype.hasOwnProperty.call(data.results, id)) {
            return !!data.results[id];
        }
        if (data && Object.prototype.hasOwnProperty.call(data, id)) {
            return !!data[id];
        }
        return true;
    }

    function getErrorMessage(response) {
        if (!response || !response.data) {
            return i18n.failed;
        }
        if (typeof response.data === 'string') {
            return response.data;
        }
        if (response.data.message) {
            return response.data.message;
        }
        return i18n.failed;
    }

    function showProgress(current, total, id) {
        const $progress = $('#pixelstamp-progress');
        if (!$progress.length) return;
        const pct = total > 0 ? Math.round((current / total) * 100) : 0;
        $progress.show();
        $('#pixelstamp-idle-status').hide();
        $('#pixelstamp-progress-fill').css('width', pct + '%');
        $('.progress-bar').attr('aria-valuenow', pct);
        $('#pixelstamp-progress-text').text(
            i18n.processing_item + ' ' + current + ' / ' + total + (id ? ' (ID: ' + id + ')' : '') + ' — ' + pct + '%'
        );
    }

    function hideProgress() {
        const $progress = $('#pixelstamp-progress');
        if (!$progress.length) return;
        $progress.hide();
        $('#pixelstamp-idle-status').show();
        $('#pixelstamp-progress-fill').css('width', '0%');
        $('.progress-bar').attr('aria-valuenow', 0);
    }

    function refreshPreviewCache() {
        const src = $previewImgAfter.attr('src');
        if (!src || !$previewImgAfter.is(':visible')) {
            return;
        }
        const base = src.split('?')[0];
        const busted = base + '?pixelstamp=' + Date.now();
        $previewImgBefore.attr('src', busted);
        $previewImgAfter.attr('src', busted);
        setTimeout(updateLivePreview, 150);
    }

    async function processBatch(ids, action) {
        if (!ids || ids.length === 0) {
            showToast(i18n.no_images || 'Please select at least one image.', 'error');
            return;
        }

        const settings = {
            text: $('#wm-text').val(),
            font: $('#wm-font').val(),
            size: $('#wm-size').val(),
            opacity: $('#wm-opacity').val(),
            color: $('#wm-color').val(),
            position: $('#wm-position').val(),
            use_box: $('#wm-use-box').is(':checked') ? 1 : 0,
            box_bg: $('#wm-box-bg').val(),
            box_border: $('#wm-box-border').val(),
            box_padding: $('#wm-box-padding').val(),
            box_radius: $('#wm-box-radius').val(),
            offset_x: $('#wm-offset-x').val(),
            offset_y: $('#wm-offset-y').val(),
            rotation: $('#wm-rotation').val(),
            scale: $('#wm-scale').val(),
            auto_apply: $('#wm-auto-apply').is(':checked') ? 1 : 0
        };

        $('#apply-btn, #restore-btn, #select-images-btn, #apply-all-btn').prop('disabled', true);
        const isRestore = action === 'pixelstamp_restore';
        showToast(isRestore ? i18n.restoring : i18n.applying, 'info');
        showProgress(0, ids.length);

        const total = ids.length;
        let successCount = 0;
        const previewId = selectedIds.length > 0 ? selectedIds[0] : null;

        for (let i = 0; i < total; i++) {
            const id = ids[i];
            showProgress(i + 1, total, id);

            try {
                const response = await $.ajax({
                    url: pixelstamp_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: pixelstamp_vars.nonce,
                        ids: [id],
                        settings: settings
                    }
                });

                if (getResultForId(response, id)) {
                    successCount++;
                    if (previewId && id === previewId) {
                        refreshPreviewCache();
                    }
                    if (total === 1) {
                        showToast(i18n.success + ' (ID: ' + id + ')', 'success');
                    }
                } else {
                    showToast(i18n.error + ' ID ' + id + ': ' + getErrorMessage(response), 'error');
                }
            } catch (err) {
                const message = (err.responseJSON && getErrorMessage(err.responseJSON)) || err.statusText || i18n.failed;
                showToast(i18n.error + ' ID ' + id + ': ' + message, 'error');
            }
        }

        hideProgress();
        const completedMessage = formatCompleteMessage(successCount, total);
        showToast(completedMessage, successCount === total ? 'success' : (successCount > 0 ? 'info' : 'error'));
        $('#apply-btn, #restore-btn, #select-images-btn, #apply-all-btn').prop('disabled', false);
    }

    function showToast(msg, type = 'info') {
        const $toast = $('#pixelstamp-toast');
        if (!$toast.length) {
            return;
        }
        $toast.text(msg).removeClass('success error info show').addClass(type).addClass('show');
        clearTimeout($toast.data('hideTimer'));
        const timer = setTimeout(() => $toast.removeClass('show'), 4000);
        $toast.data('hideTimer', timer);
    }
});
