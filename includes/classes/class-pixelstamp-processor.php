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
        $size_percent = intval($settings['size']) / 100;
        $opacity = floatval($settings['opacity']);
        $color_hex = $settings['color'];
        $position = $settings['position'];
        $use_box = !empty($settings['use_box']);
        $box_bg_hex = $settings['box_bg'] ?? '#000000';
        $box_border_hex = $settings['box_border'] ?? '#ffffff';
        $box_padding_val = intval($settings['box_padding'] ?? 10);
        $box_radius_val = intval($settings['box_radius'] ?? 0);

        // Calculate font size (more sane: percentage of image width)
        $font_size = ($width * intval($settings['size'] ?? 20)) / 400;
        if ($font_size < 10) $font_size = 10; // Minimum size


        // Font Selection (supports plugin fonts + common OS font locations).
        $font_path = self::resolve_working_ttf_font($font_choice, $font_size);
        $use_ttf = ($font_path !== '');

        // Colors
        $rgb = self::hex2rgb($color_hex);
        $text_color = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], (1 - $opacity) * 127);

        $box_rgb = self::hex2rgb($box_bg_hex);
        $box_color = imagecolorallocatealpha($image, $box_rgb[0], $box_rgb[1], $box_rgb[2], (1 - $opacity) * 127);

        $border_rgb = self::hex2rgb($box_border_hex);
        $border_color = imagecolorallocatealpha($image, $border_rgb[0], $border_rgb[1], $border_rgb[2], (1 - $opacity) * 127);

        // Measure dimensions
        $max_line_width = 0;
        $line_height = $font_size * 1.5;

        foreach ($lines as $line) {
            if ($use_ttf) {
                $bbox = @imagettfbbox($font_size, 0, $font_path, $line);
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

        // Relative Padding and Margin
        $padding = ($width / 1000) * $box_padding_val;
        $radius = ($width / 1000) * $box_radius_val;
        $margin = ($width / 100); // 1% margin from edge

        $box_w = $max_line_width + ($padding * 2);
        $box_h = $total_height + ($padding * 2);

        // Position
        switch ($position) {
            case 'top-left':
                $x = $margin; $y = $margin; break;
            case 'top-right':
                $x = $width - $box_w - $margin; $y = $margin; break;
            case 'bottom-left':
                $x = $margin; $y = $height - $box_h - $margin; break;
            case 'bottom-right':
                $x = $width - $box_w - $margin; $y = $height - $box_h - $margin; break;
            case 'center':
                $x = ($width - $box_w) / 2; $y = ($height - $box_h) / 2; break;
            default:
                $x = $width - $box_w - $margin; $y = $height - $box_h - $margin;
        }

        // Draw Box
        if ($use_box) {
            if ($radius > 0) {
                self::imagefilledroundedrectangle($image, $x, $y, $x + $box_w, $y + $box_h, $radius, $box_color);
                self::imageroundedrectangle($image, $x, $y, $x + $box_w, $y + $box_h, $radius, $border_color);
            } else {
                imagefilledrectangle($image, $x, $y, $x + $box_w, $y + $box_h, $box_color);
                imagerectangle($image, $x, $y, $x + $box_w, $y + $box_h, $border_color);
            }
        }

        // Draw Text
        foreach ($lines as $i => $line) {
            // Adjust vertical alignment for TTF
            $ly = $y + $padding + ($i * $line_height) + ($use_ttf ? ($font_size * 1.1) : 0);
            if ($use_ttf) {
                $bbox = @imagettfbbox($font_size, 0, $font_path, $line);
                if ($bbox !== false) {
                    $lw = abs($bbox[2] - $bbox[0]);
                    $lx = $x + $padding + ($max_line_width - $lw) / 2;
                    $drawn = @imagettftext($image, $font_size, 0, $lx, $ly, $text_color, $font_path, $line);
                    if ($drawn === false) {
                        $fallback = self::normalize_line_for_builtin_font($line);
                        $lw = strlen($fallback) * imagefontwidth(5);
                        $lx = $x + $padding + ($max_line_width - $lw) / 2;
                        imagestring($image, 5, $lx, $ly, $fallback, $text_color);
                    }
                } else {
                    $fallback = self::normalize_line_for_builtin_font($line);
                    $lw = strlen($fallback) * imagefontwidth(5);
                    $lx = $padding + ($max_line_width - $lw) / 2;
                    imagestring($temp_box, 5, (int)$lx, (int)$ly, $fallback, $text_color);
                }
            } else {
                $fallback = self::normalize_line_for_builtin_font($line);
                $lw = strlen($fallback) * imagefontwidth(5);
                $lx = $padding + ($max_line_width - $lw) / 2;
                imagestring($temp_box, 5, (int)$lx, (int)$ly, $fallback, $text_color);
            }
        }

        imagecopymerge($image, $temp_box, (int)$x, (int)$y, 0, 0, (int)ceil($box_w), (int)ceil($box_h), 100);
        imagedestroy($temp_box);

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
        return [
            'inter' => [
                $plugin_fonts . 'Inter-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
                'C:\\Windows\\Fonts\\arialbd.ttf',
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
