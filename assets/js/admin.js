jQuery(document).ready(function($) {
    let mediaUploader;
    let selectedIds = [];
    let currentPreviewAttachment = null;
    let showingAppliedServerImage = false;
    const i18n = pixelstamp_vars.i18n;

    // Elements
    const $previewPlaceholder = $('#preview-placeholder');
    const $previewPlaceholderAfter = $('#preview-placeholder-after');
    const $previewImgBefore = $('#preview-img-before');
    const $previewImgAfter = $('#preview-img-after');
    const $watermarkOverlay = $('#watermark-preview-overlay');

    const fontMap = {
        'inter': "'Inter', sans-serif",
        'arial': "Arial, sans-serif",
        'times': "'Times New Roman', serif",
        'courier': "'Courier New', monospace"
    };

    // Trigger initial preview on load and after web fonts finish loading
    updateLivePreview();
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function() {
            updateLivePreview();
        });
    }

    // Automatically update preview on image load
    $previewImgAfter.on('load', function() {
        updateLivePreview();
    });

    function getCleanOriginalUrl(attachmentId, fallbackUrl) {
        if (!attachmentId) return fallbackUrl || '';
        return pixelstamp_vars.ajax_url +
            '?action=pixelstamp_original_image&id=' + encodeURIComponent(attachmentId) +
            '&nonce=' + encodeURIComponent(pixelstamp_vars.nonce);
    }

    // Media Uploader
    $(document).on('click', '#select-images-btn, .pixelstamp-select-trigger', function(e) {
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

            selectedIds = attachments.map(function(a) { return a.id; });
            if (selectedIds.length > 0) {
                $('#selected-images-count').html('<strong>' + selectedIds.length + i18n.selected + '</strong>');
                currentPreviewAttachment = attachments[0];
                showingAppliedServerImage = false;
                updatePreviewImage(currentPreviewAttachment);
                $('#apply-btn, #restore-btn, .pixelstamp-apply-trigger, .pixelstamp-restore-trigger').prop('disabled', false);
            } else {
                resetPreview();
                $('#apply-btn, #restore-btn, .pixelstamp-apply-trigger, .pixelstamp-restore-trigger').prop('disabled', true);
            }
        });

        mediaUploader.open();
    });

    function updatePreviewImage(attachment) {
        if (!attachment) return;
        const cleanUrl = getCleanOriginalUrl(attachment.id, attachment.url) + '&t=' + Date.now();
        $previewPlaceholder.hide();
        $previewPlaceholderAfter.hide();
        $previewImgBefore.attr('src', cleanUrl).show();
        $previewImgAfter.attr('src', cleanUrl).show();
        setTimeout(updateLivePreview, 80);
    }

    function resetPreview() {
        currentPreviewAttachment = null;
        showingAppliedServerImage = false;
        $previewPlaceholder.show();
        $previewPlaceholderAfter.show();
        $previewImgBefore.hide().attr('src', '');
        $previewImgAfter.hide().attr('src', '');
        $watermarkOverlay.hide();
        $('#selected-images-count').html('<strong>0' + i18n.selected + '</strong>');
    }

    function getCurrentSettings() {
        return {
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
    }

    function drawRoundedRectPath(ctx, x, y, w, h, r) {
        const maxR = Math.max(0, Math.min(r, w / 2, h / 2));
        ctx.beginPath();
        ctx.moveTo(x + maxR, y);
        ctx.lineTo(x + w - maxR, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + maxR);
        ctx.lineTo(x + w, y + h - maxR);
        ctx.quadraticCurveTo(x + w, y + h, x + w - maxR, y + h);
        ctx.lineTo(x + maxR, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - maxR);
        ctx.lineTo(x, y + maxR);
        ctx.quadraticCurveTo(x, y, x + maxR, y);
        ctx.closePath();
    }

    /**
     * Unified Canvas Watermark Renderer used by both Live Preview and Server Stamp.
     * Guarantees 100% pixel-identical typography, box styling, opacity, and rotation.
     */
    function renderWatermarkStamp(settings, refWidth, dpr) {
        const rawText = String(settings.text || '').trim();
        if (!rawText) return null;

        const pixelRatio = dpr || 1;
        const lines = rawText.split(/\r?\n/);
        const fontFamily = fontMap[settings.font] || fontMap.inter;
        const sizeVal = Math.max(8, parseInt(settings.size, 10) || 20);
        const scale = Math.max(0.1, parseFloat(settings.scale) || 1);
        const parsedOpacity = parseFloat(settings.opacity);
        const opacity = Math.max(0, Math.min(1, isNaN(parsedOpacity) ? 0.8 : parsedOpacity));

        const fontSize = ((refWidth * sizeVal) / 400) * scale;
        const lineHeight = fontSize * 1.4;
        const useBox = !!settings.use_box && settings.use_box !== '0' && settings.use_box !== 0;
        const boxPadding = parseInt(settings.box_padding, 10) || 0;
        const boxRadius = parseInt(settings.box_radius, 10) || 0;

        const padding = useBox ? ((refWidth / 1000) * boxPadding * scale) : 0;
        const radius = useBox ? ((refWidth / 1000) * boxRadius * scale) : 0;
        const padX = useBox ? (padding + radius * 0.65) : 0;
        const padY = useBox ? padding : 0;
        const borderW = useBox ? Math.max(1, refWidth / 400) : 0;

        const measureCanvas = document.createElement('canvas');
        const mCtx = measureCanvas.getContext('2d');
        mCtx.font = '400 ' + fontSize + 'px ' + fontFamily;

        let maxLineWidth = 0;
        for (let i = 0; i < lines.length; i++) {
            const w = mCtx.measureText(lines[i]).width;
            if (w > maxLineWidth) maxLineWidth = w;
        }

        const boxW = Math.max(4, Math.ceil(maxLineWidth + padX * 2 + borderW * 2));
        const boxH = Math.max(4, Math.ceil(lines.length * lineHeight + padY * 2 + borderW * 2));

        const rotDeg = parseInt(settings.rotation, 10) || 0;
        const rad = (rotDeg * Math.PI) / 180;
        const cos = Math.abs(Math.cos(rad));
        const sin = Math.abs(Math.sin(rad));

        const rotW = Math.ceil(boxW * cos + boxH * sin) + 4;
        const rotH = Math.ceil(boxW * sin + boxH * cos) + 4;

        // First draw unrotated badge onto an offscreen canvas at full alpha, then composite at target opacity
        const badgeCanvas = document.createElement('canvas');
        badgeCanvas.width = Math.ceil(rotW * pixelRatio);
        badgeCanvas.height = Math.ceil(rotH * pixelRatio);
        const bCtx = badgeCanvas.getContext('2d');
        bCtx.scale(pixelRatio, pixelRatio);

        bCtx.translate(rotW / 2, rotH / 2);
        bCtx.rotate(rad);

        if (useBox) {
            const innerW = boxW - borderW;
            const innerH = boxH - borderW;
            drawRoundedRectPath(bCtx, -innerW / 2, -innerH / 2, innerW, innerH, radius);
            bCtx.fillStyle = settings.box_bg || '#000000';
            bCtx.fill();

            bCtx.lineWidth = borderW;
            bCtx.strokeStyle = settings.box_border || '#ffffff';
            bCtx.stroke();
        }

        bCtx.font = '400 ' + fontSize + 'px ' + fontFamily;
        bCtx.fillStyle = settings.color || '#ffffff';
        bCtx.textAlign = 'center';
        bCtx.textBaseline = 'middle';

        for (let i = 0; i < lines.length; i++) {
            const lineY = -boxH / 2 + borderW + padY + (i + 0.5) * lineHeight;
            bCtx.fillText(lines[i], 0, lineY);
        }

        // Apply group opacity onto final output canvas (matches CSS element opacity behavior)
        const outCanvas = document.createElement('canvas');
        outCanvas.width = badgeCanvas.width;
        outCanvas.height = badgeCanvas.height;
        const outCtx = outCanvas.getContext('2d');
        outCtx.globalAlpha = opacity;
        outCtx.drawImage(badgeCanvas, 0, 0);

        return {
            canvas: outCanvas,
            refWidth: refWidth,
            boxW: boxW,
            boxH: boxH,
            rotW: rotW,
            rotH: rotH
        };
    }

    function updateLivePreview() {
        const settings = getCurrentSettings();
        const useBox = !!settings.use_box;

        if (useBox) {
            $('#box-settings-container').slideDown(150);
        } else {
            $('#box-settings-container').slideUp(150);
        }

        const dpr = window.devicePixelRatio || 1;

        // 1. Update Focused Badge Preview
        const $focusedOverlay = $('#focused-watermark-overlay');
        const focusedWidth = $('#focused-watermark-preview-container').width() || 320;
        const focusedStamp = renderWatermarkStamp(settings, focusedWidth, dpr);

        $focusedOverlay.empty().attr('style', 'display:flex;align-items:center;justify-content:center;');
        if (focusedStamp) {
            $(focusedStamp.canvas).css({
                width: focusedStamp.rotW + 'px',
                height: focusedStamp.rotH + 'px',
                maxWidth: '100%',
                height: 'auto',
                display: 'block'
            });
            $focusedOverlay.append(focusedStamp.canvas);
        }

        // 2. Update Main Image Preview (AFTER)
        if (!$previewImgAfter.is(':visible')) {
            $watermarkOverlay.hide();
            return;
        }

        // If currently showing the actual server-watermarked file right after Apply, don't double-overlay
        if (showingAppliedServerImage) {
            $watermarkOverlay.hide();
            return;
        }

        const displayedWidth = $previewImgAfter.width();
        const displayedHeight = $previewImgAfter.height();
        if (!displayedWidth || !displayedHeight) {
            $watermarkOverlay.hide();
            return;
        }

        const previewStamp = renderWatermarkStamp(settings, displayedWidth, dpr);
        if (!previewStamp) {
            $watermarkOverlay.hide();
            return;
        }

        const margin = displayedWidth * 0.02;
        const offsetX = parseInt(settings.offset_x, 10) || 0;
        const offsetY = parseInt(settings.offset_y, 10) || 0;
        const scaledOffsetX = (displayedWidth / 400) * offsetX;
        const scaledOffsetY = (displayedWidth / 400) * offsetY;

        let x = 0;
        let y = 0;
        switch (settings.position) {
            case 'top-left':
                x = margin + scaledOffsetX;
                y = margin + scaledOffsetY;
                break;
            case 'top-right':
                x = displayedWidth - previewStamp.boxW - margin - scaledOffsetX;
                y = margin + scaledOffsetY;
                break;
            case 'bottom-left':
                x = margin + scaledOffsetX;
                y = displayedHeight - previewStamp.boxH - margin - scaledOffsetY;
                break;
            case 'bottom-right':
                x = displayedWidth - previewStamp.boxW - margin - scaledOffsetX;
                y = displayedHeight - previewStamp.boxH - margin - scaledOffsetY;
                break;
            case 'center':
                x = (displayedWidth - previewStamp.boxW) / 2 + scaledOffsetX;
                y = (displayedHeight - previewStamp.boxH) / 2 + scaledOffsetY;
                break;
            default:
                x = displayedWidth - previewStamp.boxW - margin - scaledOffsetX;
                y = displayedHeight - previewStamp.boxH - margin - scaledOffsetY;
        }

        const pasteX = x + previewStamp.boxW / 2 - previewStamp.rotW / 2;
        const pasteY = y + previewStamp.boxH / 2 - previewStamp.rotH / 2;

        $(previewStamp.canvas).css({
            width: previewStamp.rotW + 'px',
            height: previewStamp.rotH + 'px',
            display: 'block'
        });

        $watermarkOverlay
            .empty()
            .append(previewStamp.canvas)
            .css({
                top: pasteY + 'px',
                left: pasteX + 'px',
                right: 'auto',
                bottom: 'auto',
                width: previewStamp.rotW + 'px',
                height: previewStamp.rotH + 'px',
                background: 'transparent',
                border: 'none',
                padding: '0',
                opacity: '1',
                transform: 'none'
            })
            .show();
    }

    // Settings listeners
    $('#wm-text, #wm-font, #wm-size, #wm-opacity, #wm-color, #wm-position, #wm-use-box, #wm-box-bg, #wm-box-border, #wm-box-padding, #wm-box-radius, #wm-offset-x, #wm-offset-y, #wm-rotation, #wm-scale').on('input change', function() {
        if (showingAppliedServerImage && currentPreviewAttachment) {
            showingAppliedServerImage = false;
            const cleanUrl = getCleanOriginalUrl(currentPreviewAttachment.id, currentPreviewAttachment.url) + '&t=' + Date.now();
            $previewImgAfter.attr('src', cleanUrl);
        }
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
        const settings = getCurrentSettings();

        $.ajax({
            url: pixelstamp_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'pixelstamp_save_settings',
                nonce: pixelstamp_vars.nonce,
                settings: settings
            }
        });
    }

    // Apply Watermark
    $(document).on('click', '#apply-btn, .pixelstamp-apply-trigger', function() {
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
    $(document).on('click', '#restore-btn, .pixelstamp-restore-trigger', function() {
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
        $('#pixelstamp-progress-fill').css('width', '0%');
        $('.progress-bar').attr('aria-valuenow', 0);
    }

    function refreshPreviewAfterAction(isRestore) {
        if (!currentPreviewAttachment || !$previewImgAfter.is(':visible')) {
            return;
        }
        const cleanOriginalUrl = getCleanOriginalUrl(currentPreviewAttachment.id, currentPreviewAttachment.url) + '&t=' + Date.now();
        $previewImgBefore.attr('src', cleanOriginalUrl);

        if (isRestore) {
            showingAppliedServerImage = false;
            $previewImgAfter.attr('src', cleanOriginalUrl);
            setTimeout(updateLivePreview, 100);
        } else {
            // Load the actual newly-watermarked image from server and hide overlay so there is no double-render
            showingAppliedServerImage = true;
            $watermarkOverlay.hide();
            const base = currentPreviewAttachment.url.split('?')[0];
            $previewImgAfter.attr('src', base + '?pixelstamp=' + Date.now());
        }
    }

    async function processBatch(ids, action) {
        if (!ids || ids.length === 0) {
            showToast(i18n.no_images || 'Please select at least one image.', 'error');
            return;
        }

        const settings = getCurrentSettings();
        const isRestore = action === 'pixelstamp_restore';

        // Render high-resolution stamp (refWidth = 1600) so server applies the exact previewed watermark
        let stampPayload = null;
        if (!isRestore) {
            const stampRender = renderWatermarkStamp(settings, 1600, 1);
            if (stampRender && stampRender.canvas) {
                stampPayload = {
                    png_base64: stampRender.canvas.toDataURL('image/png'),
                    ref_width: stampRender.refWidth,
                    box_w: stampRender.boxW,
                    box_h: stampRender.boxH,
                    rot_w: stampRender.rotW,
                    rot_h: stampRender.rotH
                };
            }
        }

        $('#apply-btn, #restore-btn, #select-images-btn, #apply-all-btn, .pixelstamp-apply-trigger, .pixelstamp-restore-trigger, .pixelstamp-select-trigger').prop('disabled', true);
        showToast(isRestore ? i18n.restoring : i18n.applying, 'info');
        showProgress(0, ids.length);

        const total = ids.length;
        let successCount = 0;
        const previewId = currentPreviewAttachment ? currentPreviewAttachment.id : (selectedIds.length > 0 ? selectedIds[0] : null);

        for (let i = 0; i < total; i++) {
            const id = ids[i];
            showProgress(i + 1, total, id);

            try {
                const payload = {
                    action: action,
                    nonce: pixelstamp_vars.nonce,
                    ids: [id],
                    settings: settings
                };
                if (stampPayload) {
                    payload.stamp = stampPayload;
                }

                const response = await $.ajax({
                    url: pixelstamp_vars.ajax_url,
                    type: 'POST',
                    data: payload
                });

                if (getResultForId(response, id)) {
                    successCount++;
                    if (previewId && String(id) === String(previewId)) {
                        refreshPreviewAfterAction(isRestore);
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
        $('#select-images-btn, #apply-all-btn, .pixelstamp-select-trigger').prop('disabled', false);
        if (selectedIds.length > 0) {
            $('#apply-btn, #restore-btn, .pixelstamp-apply-trigger, .pixelstamp-restore-trigger').prop('disabled', false);
        }
    }

    function showToast(msg, type = 'info') {
        const $toast = $('#pixelstamp-toast');
        if (!$toast.length) {
            return;
        }
        $toast.text(msg).removeClass('success error info show').addClass(type).addClass('show');
        clearTimeout($toast.data('hideTimer'));
        const timer = setTimeout(function() { $toast.removeClass('show'); }, 4000);
        $toast.data('hideTimer', timer);
    }
});
