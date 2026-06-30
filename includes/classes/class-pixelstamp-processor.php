<?php
if (!defined('ABSPATH')) exit;

class PixelStampProcessor {
    public static function can_use_ttf() {
        return function_exists('imagettftext') && function_exists('imagettfbbox');
    }

    public static function apply($file_path, $settings) {
        $info = getimagesize($file_path);
        if (!$info) return false;

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file_path);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($file_path);
                break;
            default:
                return false;
        }

        if (!$image) return false;

        $width = imagesx($image);
        $height = imagesy($image);

        // Settings
        $text = trim((string) ($settings['text'] ?? ''));
        if ($text === '') {
            return false;
        }
        $font_choice = $settings['font'] ?? 'inter';
        $lines = explode("\n", $text);
        $opacity = floatval($settings['opacity']);
        $color_hex = $settings['color'];
        $position = $settings['position'];
        $use_box = !empty($settings['use_box']);
        $box_bg_hex = $settings['box_bg'] ?? '#000000';
        $box_border_hex = $settings['box_border'] ?? '#ffffff';
        $box_padding_val = intval($settings['box_padding'] ?? 10);
        $box_radius_val = intval($settings['box_radius'] ?? 0);
        $offset_x = intval($settings['offset_x'] ?? 0);
        $offset_y = intval($settings['offset_y'] ?? 0);
        $rotation = intval($settings['rotation'] ?? 0);
        $scale = floatval($settings['scale'] ?? 1);
        if ($scale <= 0) $scale = 1;

        // Calculate font size — matches preview: (width * size) / 400 * scale
        $font_size = ($width * intval($settings['size'] ?? 20)) / 400;
        if ($font_size < 8) $font_size = 8; // Minimum size — matches JS preview minimum of 8

        // Apply scale to font size
        $font_size = $font_size * $scale;

        // GD's imagettftext $size parameter behaves as approximate pixel height of the
        // em-square (it internally uses 72 DPI assumption, but the rendered glyph size
        // matches the numeric value in pixels). CSS font-size:Xpx also sets the em-square
        // height to X pixels. So we use $font_size directly — no conversion needed.
        $font_size_pt = $font_size;

        // Font Selection (supports plugin fonts + common OS font locations).
        $font_path = self::resolve_working_ttf_font($font_choice, $font_size_pt);
        $use_ttf = ($font_path !== '');

        // Colors — resolved here; actual allocation happens on temp canvases with full opacity.
        // We use imagecopymerge() for opacity compositing because JPEG does NOT support
        // alpha channels — imagecolorallocatealpha() alpha is silently discarded on save.
        $rgb = self::hex2rgb($color_hex);
        $box_rgb = self::hex2rgb($box_bg_hex);
        $border_rgb = self::hex2rgb($box_border_hex);

        // Merge percentage for imagecopymerge (0–100)
        $merge_pct = max(0, min(100, (int)($opacity * 100)));

        // Measure dimensions — line-height 1.4 matches CSS .watermark-overlay
        $max_line_width = 0;
        $line_height = $font_size * 1.4;

        foreach ($lines as $line) {
            if ($use_ttf) {
                $bbox = @imagettfbbox($font_size_pt, 0, $font_path, $line);
                if ($bbox !== false) {
                    $w = abs($bbox[2] - $bbox[0]);
                } else {
                    $w = strlen(self::normalize_line_for_builtin_font($line)) * imagefontwidth(5);
                }
            } else {
                $w = strlen(self::normalize_line_for_builtin_font($line)) * imagefontwidth(5);
            }
            if ($w > $max_line_width) $max_line_width = $w;
        }

        $total_height = count($lines) * $line_height;

        // Relative Padding and Margin — matches preview proportions
        $padding = ($width / 1000) * $box_padding_val * $scale;
        $radius = ($width / 1000) * $box_radius_val * $scale;
        // Margin: preview uses 8px at display size. For proportional match,
        // use 2% of image width (equivalent to ~8px at typical preview widths)
        $margin = $width * 0.02;

        // Scale offsets proportionally to image width
        // Preview offsets are in CSS pixels relative to displayed image
        // Convert to actual image pixels
        $scaled_offset_x = ($width / 400) * $offset_x;
        $scaled_offset_y = ($width / 400) * $offset_y;

        $box_w = $max_line_width + ($padding * 2);
        $box_h = $total_height + ($padding * 2);

        // Position — matches preview switch/case logic
        switch ($position) {
            case 'top-left':
                $x = $margin + $scaled_offset_x;
                $y = $margin + $scaled_offset_y;
                break;
            case 'top-right':
                $x = $width - $box_w - $margin + $scaled_offset_x;
                $y = $margin + $scaled_offset_y;
                break;
            case 'bottom-left':
                $x = $margin + $scaled_offset_x;
                $y = $height - $box_h - $margin + $scaled_offset_y;
                break;
            case 'bottom-right':
                $x = $width - $box_w - $margin + $scaled_offset_x;
                $y = $height - $box_h - $margin + $scaled_offset_y;
                break;
            case 'center':
                $x = ($width - $box_w) / 2 + $scaled_offset_x;
                $y = ($height - $box_h) / 2 + $scaled_offset_y;
                break;
            default:
                $x = $width - $box_w - $margin + $scaled_offset_x;
                $y = $height - $box_h - $margin + $scaled_offset_y;
        }

        // Apply watermark using a compositing approach that works for ALL image formats.
        //
        // JPEG does NOT support alpha channels — imagecolorallocatealpha() alpha values
        // are silently discarded on imagejpeg() save. So we composite via imagecopymerge().
        //
        // OPACITY MODEL (must match the JS/CSS preview exactly):
        //   In admin.js the overlay gets CSS `opacity: <opacity>` on the whole element,
        //   AND the box background is pre-multiplied via hexToRgba(boxBg, opacity). So the
        //   effective opacities are:
        //     - Box background: opacity * opacity  (double-applied)
        //     - Text + border:  opacity            (single)
        //
        //   imagecopymerge() blends the ENTIRE region at one percentage, so we do it in two
        //   passes on a temp canvas that starts as a copy of the original pixels (so non-drawn
        //   pixels blend to themselves = unchanged):
        //     Pass 1: draw box background only → merge at opacity²
        //     Pass 2: draw text + border only  → merge at opacity
        $is_jpeg = ($mime === 'image/jpeg');
        $box_merge_pct  = max(0, min(100, (int)($opacity * $opacity * 100))); // background effective opacity
        $text_merge_pct = $merge_pct;                                        // text + border effective opacity

        if ($rotation != 0 && function_exists('imagerotate')) {
            // Rotation path: build a temp with box + text at full opacity, rotate, then
            // two-pass merge back. (Rounded corners + rotation make per-element opacity
            // passes impractical, so we approximate with a single merge at text opacity.)
            $canvas_w = (int)ceil($box_w) + 4;
            $canvas_h = (int)ceil($box_h) + 4;

            $temp = imagecreatetruecolor($canvas_w, $canvas_h);
            imagesavealpha($temp, true);
            imagealphablending($temp, false);
            $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
            imagefill($temp, 0, 0, $transparent);
            imagealphablending($temp, true);

            $wm_text_color  = imagecolorallocate($temp, $rgb[0], $rgb[1], $rgb[2]);
            $wm_box_color   = imagecolorallocate($temp, $box_rgb[0], $box_rgb[1], $box_rgb[2]);
            $wm_border_color = imagecolorallocate($temp, $border_rgb[0], $border_rgb[1], $border_rgb[2]);

            if ($use_box) {
                if ($radius > 0) {
                    self::imagefilledroundedrectangle($temp, 2, 2, 2 + $box_w, 2 + $box_h, $radius, $wm_box_color);
                    self::imageroundedrectangle($temp, 2, 2, 2 + $box_w, 2 + $box_h, $radius, $wm_border_color);
                } else {
                    imagefilledrectangle($temp, 2, 2, (int)(2 + $box_w), (int)(2 + $box_h), $wm_box_color);
                    imagerectangle($temp, 2, 2, (int)(2 + $box_w), (int)(2 + $box_h), $wm_border_color);
                }
            }

            self::draw_text_lines($temp, $lines, $use_ttf, $font_size, $font_path, 2, 2, $padding, $line_height, $max_line_width, $wm_text_color);

            $rotated = imagerotate($temp, -$rotation, $transparent);
            imagesavealpha($rotated, true);
            imagedestroy($temp);

            $rot_w = imagesx($rotated);
            $rot_h = imagesy($rotated);
            $paste_x = (int)($x + $box_w / 2 - $rot_w / 2);
            $paste_y = (int)($y + $box_h / 2 - $rot_h / 2);

            imagealphablending($image, true);
            imagesavealpha($image, !$is_jpeg);
            imagecopymerge($image, $rotated, $paste_x, $paste_y, 0, 0, $rot_w, $rot_h, $text_merge_pct);
            imagedestroy($rotated);
        } else {
            // Non-rotation path: two-pass merge for exact opacity-model match.
            $layer_w = (int)ceil($box_w) + 2;
            $layer_h = (int)ceil($box_h) + 2;
            $paste_x = (int)$x;
            $paste_y = (int)$y;

            imagealphablending($image, true);
            imagesavealpha($image, !$is_jpeg);

            // --- Pass 1: box background at opacity² ---
            if ($use_box) {
                $pass1 = imagecreatetruecolor($layer_w, $layer_h);
                imagecopy($pass1, $image, 0, 0, $paste_x, $paste_y, $layer_w, $layer_h);
                imagealphablending($pass1, true);

                $p1_box_color = imagecolorallocate($pass1, $box_rgb[0], $box_rgb[1], $box_rgb[2]);
                if ($radius > 0) {
                    self::imagefilledroundedrectangle($pass1, 1, 1, 1 + $box_w, 1 + $box_h, $radius, $p1_box_color);
                } else {
                    imagefilledrectangle($pass1, 1, 1, (int)(1 + $box_w), (int)(1 + $box_h), $p1_box_color);
                }

                imagecopymerge($image, $pass1, $paste_x, $paste_y, 0, 0, $layer_w, $layer_h, $box_merge_pct);
                imagedestroy($pass1);
            }

            // --- Pass 2: text + border at opacity ---
            $pass2 = imagecreatetruecolor($layer_w, $layer_h);
            imagecopy($pass2, $image, 0, 0, $paste_x, $paste_y, $layer_w, $layer_h);
            imagealphablending($pass2, true);

            $p2_text_color  = imagecolorallocate($pass2, $rgb[0], $rgb[1], $rgb[2]);
            $p2_border_color = imagecolorallocate($pass2, $border_rgb[0], $border_rgb[1], $border_rgb[2]);

            if ($use_box) {
                if ($radius > 0) {
                    self::imageroundedrectangle($pass2, 1, 1, 1 + $box_w, 1 + $box_h, $radius, $p2_border_color);
                } else {
                    imagerectangle($pass2, 1, 1, (int)(1 + $box_w), (int)(1 + $box_h), $p2_border_color);
                }
            }

            self::draw_text_lines($pass2, $lines, $use_ttf, $font_size, $font_path, 1, 1, $padding, $line_height, $max_line_width, $p2_text_color);

            imagecopymerge($image, $pass2, $paste_x, $paste_y, 0, 0, $layer_w, $layer_h, $text_merge_pct);
            imagedestroy($pass2);
        }

        // Save back
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($image, $file_path, 90);
                break;
            case 'image/png':
                imagepng($image, $file_path);
                break;
            case 'image/webp':
                imagewebp($image, $file_path, 80);
                break;
        }

        imagedestroy($image);
        return true;
    }

    /**
     * Draw text lines on an image resource.
     */
    private static function draw_text_lines($image, $lines, $use_ttf, $font_size, $font_path, $base_x, $base_y, $padding, $line_height, $max_line_width, $text_color) {
        // GD size = CSS pixel font size (no conversion needed, see apply() comment)
        $font_size_pt = $font_size;

        // Calculate baseline offset using imagettfbbox ascent for proper vertical
        // centering within the line-height. CSS line-height:1.4 centers text with
        // equal spacing above and below. We use the font's actual ascent to compute
        // the correct baseline Y position inside each line slot.
        $baseline_offset = 0;
        if ($use_ttf) {
            $probe_bbox = @imagettfbbox($font_size_pt, 0, $font_path, 'Hg|Ájy');
            if ($probe_bbox !== false) {
                // ascent = distance from baseline to top of tallest glyph (negative Y in GD)
                $ascent = abs($probe_bbox[7] - $probe_bbox[1]); // row7 = bottom-left Y, row1 = top-left Y
                // Center the text vertically in the line: (line_height - text_height) / 2
                $text_height = abs($probe_bbox[7] - $probe_bbox[1]);
                $baseline_offset = ($line_height - $text_height) / 2 + $text_height;
            } else {
                // Fallback: approximate — baseline sits ~80% down from top of em-square
                $baseline_offset = $font_size_pt * 1.0;
            }
        }

        foreach ($lines as $i => $line) {
            $ly = $base_y + $padding + ($i * $line_height) + $baseline_offset;
            if ($use_ttf) {
                $bbox = @imagettfbbox($font_size_pt, 0, $font_path, $line);
                if ($bbox !== false) {
                    $lw = abs($bbox[2] - $bbox[0]);
                    $lx = $base_x + $padding + ($max_line_width - $lw) / 2;
                    $drawn = @imagettftext($image, $font_size_pt, 0, (int)$lx, (int)$ly, $text_color, $font_path, $line);
                    if ($drawn === false) {
                        $fallback = self::normalize_line_for_builtin_font($line);
                        $lw = strlen($fallback) * imagefontwidth(5);
                        $lx = $base_x + $padding + ($max_line_width - $lw) / 2;
                        imagestring($image, 5, (int)$lx, (int)$ly, $fallback, $text_color);
                    }
                } else {
                    $fallback = self::normalize_line_for_builtin_font($line);
                    $lw = strlen($fallback) * imagefontwidth(5);
                    $lx = $base_x + $padding + ($max_line_width - $lw) / 2;
                    imagestring($image, 5, (int)$lx, (int)$ly, $fallback, $text_color);
                }
            } else {
                $fallback = self::normalize_line_for_builtin_font($line);
                $lw = strlen($fallback) * imagefontwidth(5);
                $lx = $base_x + $padding + ($max_line_width - $lw) / 2;
                imagestring($image, 5, (int)$lx, (int)$ly, $fallback, $text_color);
            }
        }
    }

    private static function imagefilledroundedrectangle($img, $x1, $y1, $x2, $y2, $radius, $color) {
        imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private static function imageroundedrectangle($img, $x1, $y1, $x2, $y2, $radius, $color) {
        imageline($img, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
        imageline($img, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
        imageline($img, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
        imageline($img, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagearc($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
        imagearc($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
    }

    private static function hex2rgb($hex) {
        $hex = str_replace("#", "", $hex);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return [$r, $g, $b];
    }

    private static function normalize_line_for_builtin_font($line) {
        $line = (string) $line;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);
            if ($converted !== false) {
                $line = $converted;
            }
        }
        // GD built-in bitmap fonts only support basic ASCII reliably.
        $line = preg_replace('/[^\x20-\x7E]/', '', $line);
        return $line !== '' ? $line : 'PixelStamp';
    }

    private static function resolve_font_path($font_choice) {
        $font_choice = sanitize_key((string) $font_choice);
        $candidates = self::get_font_candidates();

        $selected = isset($candidates[$font_choice]) ? $candidates[$font_choice] : $candidates['inter'];
        $fallback_order = array_merge($selected, $candidates['inter'], $candidates['arial']);

        foreach ($fallback_order as $path) {
            if (is_string($path) && file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    private static function can_render_with_ttf($font_path, $font_size = 20) {
        if (!self::can_use_ttf() || !is_string($font_path) || $font_path === '') {
            return false;
        }
        if (!file_exists($font_path) || !is_readable($font_path)) {
            return false;
        }
        if (filesize($font_path) <= 0) {
            return false;
        }
        $probe = @imagettfbbox((float) $font_size, 0, $font_path, 'PixelStamp 123');
        return $probe !== false;
    }

    /**
     * Probe a single font file and return detailed result with any captured error.
     */
    private static function probe_ttf_font($font_path, $font_size = 20) {
        $result = [
            'ok'    => false,
            'error' => '',
        ];

        if (!self::can_use_ttf()) {
            $result['error'] = 'imagettfbbox/imagettftext functions missing';
            return $result;
        }
        if (!is_string($font_path) || $font_path === '') {
            $result['error'] = 'Empty font path';
            return $result;
        }
        if (!file_exists($font_path)) {
            $result['error'] = 'File does not exist';
            return $result;
        }
        if (!is_readable($font_path)) {
            $result['error'] = 'File not readable (permission denied)';
            return $result;
        }
        $fsize = filesize($font_path);
        if ($fsize <= 0) {
            $result['error'] = 'File is empty (0 bytes)';
            return $result;
        }

        // Read magic bytes to validate TTF format.
        $fh = @fopen($font_path, 'rb');
        if ($fh) {
            $magic = fread($fh, 4);
            fclose($fh);
            // Valid TTF: 00 01 00 00; Valid OTF: 4F 54 54 4F ("OTTO")
            $is_ttf = ($magic === "\x00\x01\x00\x00");
            $is_otf = ($magic === "OTTO");
            if (!$is_ttf && !$is_otf) {
                $hex = strtoupper(bin2hex($magic));
                $result['error'] = "Not a valid TTF/OTF file (magic bytes: {$hex})";
                return $result;
            }
        }

        // Capture the actual error from imagettfbbox using a temporary error handler.
        $captured_error = '';
        set_error_handler(function ($errno, $errstr) use (&$captured_error) {
            $captured_error = $errstr;
            return true; // Suppress the error.
        });

        $probe = imagettfbbox((float) $font_size, 0, $font_path, 'PixelStamp 123');

        restore_error_handler();

        if ($probe !== false) {
            $result['ok'] = true;
        } else {
            $result['error'] = $captured_error !== ''
                ? $captured_error
                : 'imagettfbbox returned false (unknown reason)';
        }

        return $result;
    }

    private static function resolve_working_ttf_font($font_choice, $font_size = 20) {
        $candidate = self::resolve_font_path($font_choice);
        if (self::can_render_with_ttf($candidate, $font_size)) {
            return $candidate;
        }

        $candidates = self::get_font_candidates();
        foreach ($candidates as $paths) {
            foreach ($paths as $path) {
                if (self::can_render_with_ttf($path, $font_size)) {
                    return $path;
                }
            }
        }

        return '';
    }

    public static function get_font_candidates() {
        $plugin_fonts = PIXELSTAMP_WATERMARK_PATH . 'assets/fonts/';
        // IMPORTANT: Match the JS preview font weights exactly. admin.js uses regular-weight
        // CSS fonts ('Inter', 'Arial', 'Times New Roman', 'Courier New') — NOT bold. Using a
        // Bold TTF here would render text ~25% wider/larger than the preview. The bundled
        // Inter-Regular.ttf ships with the plugin for this exact reason.
        return [
            'inter' => [
                $plugin_fonts . 'Inter-Regular.ttf',
                $plugin_fonts . 'Inter-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
            ],
            'arial' => [
                $plugin_fonts . 'Arial.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
            ],
            'times' => [
                $plugin_fonts . 'Times.ttf',
                '/System/Library/Fonts/Supplemental/Times New Roman.ttf',
                '/Library/Fonts/Times New Roman.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
                '/usr/share/fonts/dejavu/DejaVuSerif.ttf',
                'C:\\Windows\\Fonts\\times.ttf',
            ],
            'courier' => [
                $plugin_fonts . 'Courier.ttf',
                '/System/Library/Fonts/Supplemental/Courier New.ttf',
                '/Library/Fonts/Courier New.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
                '/usr/share/fonts/dejavu/DejaVuSansMono.ttf',
                'C:\\Windows\\Fonts\\cour.ttf',
            ],
        ];
    }

    public static function get_system_status() {
        $gd_loaded = extension_loaded('gd');
        $gd_info = $gd_loaded && function_exists('gd_info') ? gd_info() : [];
        $freetype_supported = !empty($gd_info['FreeType Support']);

        $status = [
            'gd_loaded' => $gd_loaded,
            'freetype_support' => $freetype_supported,
            'ttf_functions' => self::can_use_ttf(),
            'ttf_probe_ok' => false,
            'ttf_probe_message' => '',
            'ttf_probe_error' => '',
            'fonts' => [],
        ];

        $resolved = self::resolve_font_path('inter');
        $working = self::resolve_working_ttf_font('inter', 20);
        if (!$gd_loaded) {
            $status['ttf_probe_message'] = 'PHP GD extension is not loaded.';
        } elseif (!$freetype_supported) {
            $status['ttf_probe_message'] = 'GD is loaded but FreeType Support is disabled.';
        } elseif (!$status['ttf_functions']) {
            $status['ttf_probe_message'] = 'imagettftext/imagettfbbox functions are unavailable.';
        } elseif ($resolved === '' || !file_exists($resolved)) {
            $status['ttf_probe_message'] = 'No usable TTF font file found for runtime probe.';
        } elseif ($working !== '') {
            $status['ttf_probe_ok'] = true;
            if ($working === $resolved) {
                $status['ttf_probe_message'] = 'TTF rendering probe passed.';
            } else {
                $status['ttf_probe_message'] = 'Default Inter font failed probe; using fallback TTF at runtime.';
            }
        } else {
            $status['ttf_probe_message'] = 'TTF probe failed for all discovered font files.';
        }

        if ($status['ttf_probe_ok'] && $status['ttf_probe_message'] === '') {
            $status['ttf_probe_message'] = 'TTF rendering probe passed.';
        }

        // Per-font detailed probe with error capture.
        $candidates = self::get_font_candidates();
        foreach ($candidates as $key => $paths) {
            $found = '';
            foreach ($paths as $path) {
                if (is_string($path) && file_exists($path)) {
                    $found = $path;
                    break;
                }
            }

            $probe_result = ['ok' => false, 'error' => ''];
            if ($found !== '') {
                $probe_result = self::probe_ttf_font($found, 20);
            }

            $status['fonts'][$key] = [
                'found'       => ($found !== ''),
                'path'        => $found,
                'probe_ok'    => $probe_result['ok'],
                'probe_error' => $probe_result['error'],
            ];
        }

        return $status;
    }
}
