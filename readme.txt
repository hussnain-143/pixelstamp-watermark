=== PixelStamp Watermark ===
Contributors: ahmed143
Tags: watermark, images, photo protection, auto watermark, photography
Requires at least: 7.0
Tested up to: 7
Requires PHP: 7.4
Stable tag: 1.1.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add customizable text watermarks to your images. Features auto-watermark on upload, bulk media processing, and original image restoration.

== Description ==

PixelStamp Watermark is an image protection and watermarking solution for WordPress. It helps photographers, bloggers, agencies, and shop owners protect creative work with fully customizable text watermarks.

You can watermark images automatically when they are uploaded, or process selected images—or your entire media library—in bulk from a dedicated admin screen. A live preview lets you adjust text, colors, position, and styling before you commit changes.

The plugin uses a non-destructive workflow: it creates a secure backup of each original file before applying a watermark, so you can restore the untouched image at any time with one click.

**Ideal for:**

* Photography portfolios and galleries
* Blog posts with original imagery
* WooCommerce or catalog product photos
* Stock-style sites that need consistent branding
* Teams that want automatic protection on every new upload

== Features ==

**Watermarking**

* Custom text watermarks (single or multi-line)
* Adjustable font family, size, color, and opacity
* Five placement presets: top-left, top-right, center, bottom-left, bottom-right
* Optional background box with padding, border, and corner radius
* Horizontal and vertical offset controls
* Rotation and scale controls for creative layouts

**Workflow**

* Apply to selected images from the Media Library
* Apply to all images in the library in one batch
* Auto-apply watermark on new uploads (optional)
* Restore originals from secure backups
* Real-time preview before and after comparison
* Toast notifications and progress bar during batch jobs

**Technical**

* JPEG, PNG, and WebP support
* Regenerates all WordPress image sizes after apply/restore
* Backups stored in a dedicated uploads subfolder
* Built for the WordPress admin—no external services required

== Requirements ==

Before installing, confirm your hosting environment meets these requirements:

* WordPress 7.0 or higher
* PHP 7.4 or higher (PHP 8.x recommended)
* PHP GD extension enabled (required for image processing)
* Writable `wp-content/uploads` directory
* Sufficient disk space for backup copies of processed images

**Recommended:**

* Use contrasting watermark colors on light or busy backgrounds
* Test on a single image before running “Apply to All”
* Clear site, browser, or CDN cache after bulk processing if old images still appear

== Installation ==

= Install from WordPress admin (ZIP upload) =

1. Download or build the `pixelstamp-watermark` plugin ZIP file.
2. Log in to your WordPress dashboard.
3. Go to **Plugins → Add New → Upload Plugin**.
4. Choose the ZIP file and click **Install Now**.
5. When installation completes, click **Activate Plugin**.
6. Open **PixelStamp** in the left admin menu to begin setup.

= Install manually (FTP / file manager) =

1. Extract the `pixelstamp-watermark` folder from the ZIP package.
2. Upload the entire `pixelstamp-watermark` folder to `/wp-content/plugins/` on your server.
3. Ensure the main plugin file exists at: `wp-content/plugins/pixelstamp-watermark/pixelstamp-watermark.php`
4. In WordPress, go to **Plugins** and click **Activate** under “PixelStamp Watermark”.
5. Open **PixelStamp** from the admin menu.

= Install with WP-CLI =

1. Upload or clone the plugin into `wp-content/plugins/pixelstamp-watermark/`.
2. Run: `wp plugin activate pixelstamp-watermark`
3. Open **PixelStamp** in the dashboard to configure settings.

= Verify GD is enabled =

1. Go to **Tools → Site Health → Info** (or ask your host).
2. Under **Server**, confirm the **GD** or **ImageMagick** section lists GD support.
3. If GD is missing, contact your host to enable the PHP GD extension—without it, watermarking will not work.

== Configuration ==

After activation, configure your global watermark defaults:

1. Go to **PixelStamp** in the WordPress admin sidebar.
2. Under **Configuration Settings**, enter your **Watermark Text** (e.g. © Your Name or your site URL).
3. Choose **Font Family**, **Font Size**, **Text Color**, and **Opacity**.
4. Select a **Watermark Position** (bottom-right is recommended for most photos).
5. Optionally enable **Background Box** for better readability on busy images.
6. Adjust **Spacing & Offset**, **Rotation**, and **Scale** if needed.
7. Watch the **Watermark Preview** and **Live Preview** panels update as you type.
8. Enable **Auto-apply Watermark on Upload** if you want every new image protected automatically.

Settings are saved as you change them; you do not need a separate “Save” button for the form fields.

== Usage ==

= Watermark selected images =

1. Open **PixelStamp** in the admin menu.
2. Click **Open Media Library** and select one or more images.
3. Review the live preview on the right.
4. Click **Apply Watermark**.
5. Watch the **Status & Progress** panel and toast messages for feedback.
6. Check the image in **Media → Library** or on the front end of your site.

= Watermark your entire library =

1. Configure your watermark settings first (see Configuration above).
2. Click **Apply to All** and confirm the prompt.
3. Wait for the progress bar to complete—large libraries may take several minutes.
4. Re-check a few random images to confirm the watermark looks correct.

= Restore original images =

1. Select the same image(s) in **PixelStamp**.
2. Click **Restore Originals**.
3. The plugin copies the backed-up file back and regenerates thumbnails.
4. Backups are removed after a successful restore for that file.

= Auto-apply on new uploads =

1. Enable **Auto-apply Watermark on Upload** under Global Settings.
2. Upload a new JPEG, PNG, or WebP file through **Media → Add New**.
3. The watermark is applied automatically when WordPress generates attachment metadata.

**Note:** Use the **PixelStamp** admin screen to apply watermarks. The standard “Edit Image” control in the block editor does not run this plugin’s batch tools.

== Backups & file storage ==

* Original files are copied to `wp-content/uploads/pixelstamp-watermark-backups/` before the first watermark is applied.
* Each backup uses the original filename plus a `.bak` extension.
* Restoring replaces the public file and deletes that backup entry.
* Keep adequate disk space when watermarking large libraries.

== Frequently Asked Questions ==

= Does it modify the original image? =

Yes—the watermarked version replaces the file WordPress serves, but a backup is stored first. Use **Restore Originals** to revert to the unmarked file.

= Can I watermark new uploads automatically? =

Yes. Enable **Auto-apply Watermark on Upload** in Global Settings. Every new supported image will be watermarked when it is processed by WordPress.

= Which image formats are supported? =

JPEG, PNG, and WebP. Other formats (GIF, SVG, PDF, etc.) are not processed.

= Why does my watermark not show on the site after applying? =

The plugin updates the full-size file and regenerates WordPress thumbnails (thumbnail, medium, large, etc.) after each apply. If you still see an old version:

* Hard-refresh your browser or clear browser cache
* Purge any caching or CDN plugin (e.g. LiteSpeed, WP Rocket, Cloudflare)
* Open the attachment in the Media Library and confirm you are viewing the full-size or a regenerated size

= Where do I apply watermarks? =

Open **PixelStamp** in the WordPress admin sidebar. Select images, configure settings, then click **Apply Watermark**. You will see a progress bar and toast notifications when processing finishes.

= What are the server requirements? =

PHP GD must be enabled. Upload directories must be writable. See the **Requirements** section above for the full list.

= Will watermarking slow down my site? =

Applying watermarks is done in the admin (or once on upload when auto-apply is on). Front-end visitors load normal image files—no extra processing per page view. Bulk “Apply to All” may take time on large libraries; run it during a quiet period.

= Can I use multi-line watermark text? =

Yes. Enter line breaks in the **Watermark Text** field; each line is drawn on the image.

= What happens if watermark text is empty? =

The plugin will not apply an empty watermark and will report an error instead of silently skipping visible text.

= Is this plugin compatible with page builders and WooCommerce? =

Yes. It works with standard WordPress attachments, so images used in posts, pages, builders, and WooCommerce product galleries are affected once processed—provided they use files from the Media Library.

== Changelog ==

= 1.1.7 =
* Fixed watermarks not appearing on front-end and media library thumbnails by regenerating all attachment sizes after apply and restore.
* Fixed toast notifications not showing (incorrect HTML element ID).
* Added Status & Progress panel with a live progress bar during batch processing.
* Fixed AJAX always reporting success when watermarking failed; per-image errors now surface in the UI.
* Fixed completion summary message placeholders (%1$d / %2$d).
* Preview refreshes with cache-busting after a successful apply.
* Prevented double watermarking when auto-apply runs during thumbnail regeneration.
* Rejects empty watermark text; improved color setting fallbacks.
* Default font size aligned with settings UI (20).

= 1.1.6 =
* Added Spacing, Rotation, and Scale controls.
* Enhanced real-time preview with image boundary containment.
* Improved security and WordPress coding standards compliance.
* Refined UI with toast notifications for better workflow.

= 1.0.0 =
* Initial release.
* Features bulk watermarking and auto-upload protection.
* Added support for multi-line text and background boxes.

== Upgrade Notice ==

= 1.1.7 =
Important fix: watermarks now appear on all image sizes (thumbnails, medium, large). Re-apply watermarks to existing images if they still look unmarked. Toast and progress feedback are restored.

= 1.0.0 =
Initial version of PixelStamp Watermark.

== Support ==

For setup help, review the **Installation**, **Configuration**, and **Usage** sections above. If watermarks fail to apply, verify PHP GD is enabled and that your uploads folder is writable.

