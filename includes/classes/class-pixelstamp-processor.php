<?php
if (!defined('ABSPATH')) exit;

class PixelStampProcessor {
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


        // Font Selection (Prioritize local assets)
        $font_path = PIXELSTAMP_WATERMARK_PATH . 'assets/fonts/';
        switch ($font_choice) {
            case 'arial':
                $font_path .= 'Arial.ttf';
                break;
            case 'times':
                $font_path .= 'Times.ttf';
                break;
            case 'courier':
                $font_path .= 'Courier.ttf';
                break;
            default:
                $font_path .= 'Inter-Bold.ttf';
        }


        // If font file doesn't exist, try a few fallbacks or use built-in font
        if (!file_exists($font_path)) {
            $fallbacks = [
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial Unicode.ttf',
                PIXELSTAMP_WATERMARK_PATH . 'assets/fonts/Inter-Bold.ttf'
            ];
            foreach ($fallbacks as $fb) {
                if (file_exists($fb)) {
                    $font_path = $fb;
                    break;
                }
            }
        }

        $use_ttf = file_exists($font_path);

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
                $bbox = imagettfbbox($font_size, 0, $font_path, $line);
                $w = abs($bbox[2] - $bbox[0]);
            } else {
                $w = strlen($line) * imagefontwidth(5);
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
                $bbox = imagettfbbox($font_size, 0, $font_path, $line);
                $lw = abs($bbox[2] - $bbox[0]);
                $lx = $x + $padding + ($max_line_width - $lw) / 2;
                imagettftext($image, $font_size, 0, $lx, $ly, $text_color, $font_path, $line);
            } else {
                $lw = strlen($line) * imagefontwidth(5);
                $lx = $x + $padding + ($max_line_width - $lw) / 2;
                imagestring($image, 5, $lx, $ly, $line, $text_color);
            }
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
}
