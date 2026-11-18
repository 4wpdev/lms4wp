<?php
/**
 * Promo video of a course or a module: native video (media library / URL), format (short 9:16 /
 * landscape 16:9) and a YouTube link. The featured image stays for SEO / previews and is the poster.
 *
 * Front-matter (import): video, video_format, youtube.
 *
 * @package ForWP\LMS\Content
 */

namespace ForWP\LMS\Content;

use ForWP\LMS\Frontend\Icons;
use ForWP\LMS\PostTypes\Course;
use ForWP\LMS\PostTypes\Module;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

final class PromoVideo
{
	const META_URL     = '_lms4wp_video_url';
	const META_FORMAT  = '_lms4wp_video_format';
	const META_YOUTUBE = '_lms4wp_youtube_url';

	const FORMATS = ['short', 'landscape'];

	const NONCE = 'lms4wp_promo_video';
	const SCRIPT = 'lms4wp-promo-video-admin';

	public static function init(): void
	{
		add_action('init', [self::class, 'registerMeta'], 20);
		add_action('add_meta_boxes', [self::class, 'addMetaBox']);
		add_action('save_post', [self::class, 'save'], 10, 2);
		add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
	}

	/**
	 * @return string[]
	 */
	public static function postTypes(): array
	{
		return [Course::POST_TYPE, Module::POST_TYPE];
	}

	public static function registerMeta(): void
	{
		foreach (self::postTypes() as $post_type) {
			foreach ([self::META_URL, self::META_FORMAT, self::META_YOUTUBE] as $key) {
				register_post_meta($post_type, $key, [
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn(): bool => current_user_can('edit_lms_courses'),
				]);
			}
		}
	}

	/**
	 * @return array{url: string, format: string, youtube: string}
	 */
	public static function get(int $post_id): array
	{
		$format = (string) get_post_meta($post_id, self::META_FORMAT, true);
		return [
			'url'     => (string) get_post_meta($post_id, self::META_URL, true),
			'format'  => in_array($format, self::FORMATS, true) ? $format : 'short',
			'youtube' => (string) get_post_meta($post_id, self::META_YOUTUBE, true),
		];
	}

	/**
	 * Native video or an embeddable YouTube link.
	 */
	public static function hasVideo(int $post_id): bool
	{
		$video = self::get($post_id);
		return $video['url'] !== '' || self::youtubeId($video['youtube']) !== '';
	}

	/**
	 * YouTube video ID from a watch / shorts / embed / youtu.be URL ('' when none).
	 */
	public static function youtubeId(string $url): string
	{
		$url = trim($url);
		if ($url === '' || !self::isYoutube($url)) {
			return '';
		}
		$host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
		$path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
		if (str_ends_with($host, 'youtu.be')) {
			$id = explode('/', $path)[0];
		} elseif (preg_match('#^(?:shorts|embed|live)/([^/?]+)#', $path, $m)) {
			$id = $m[1];
		} else {
			parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
			$id = (string) ($query['v'] ?? '');
		}
		return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : '';
	}

	/**
	 * Clean front-matter / form values into meta (only keys that are present).
	 *
	 * @param array<string, mixed> $values Keys: video, video_format, youtube.
	 * @return array<string, string>
	 */
	public static function metaFrom(array $values): array
	{
		$out = [];
		if (array_key_exists('video', $values)) {
			$out[self::META_URL] = esc_url_raw(trim((string) $values['video']));
		}
		if (array_key_exists('video_format', $values)) {
			$format = sanitize_key((string) $values['video_format']);
			$out[self::META_FORMAT] = in_array($format, self::FORMATS, true) ? $format : 'short';
		}
		if (array_key_exists('youtube', $values)) {
			$out[self::META_YOUTUBE] = self::isYoutube((string) $values['youtube']) ? esc_url_raw(trim((string) $values['youtube'])) : '';
		}
		return $out;
	}

	private static function isYoutube(string $url): bool
	{
		$host = strtolower((string) wp_parse_url(trim($url), PHP_URL_HOST));
		return in_array(preg_replace('/^(www\.|m\.)/', '', $host), ['youtube.com', 'youtu.be'], true);
	}

	/**
	 * Native video (poster = featured image), else the YouTube video embedded in the same frame,
	 * else the featured image alone. Empty string when there is none of them.
	 */
	public static function renderMedia(int $post_id, string $alt_class = 'lms4wp-promo__image'): string
	{
		$video = self::get($post_id);
		$title = get_the_title($post_id);
		$youtube_id = $video['url'] === '' ? self::youtubeId($video['youtube']) : '';
		if ($youtube_id !== '') {
			$embed = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($youtube_id) . '?rel=0&modestbranding=1&playsinline=1';
			return sprintf(
				'<figure class="lms4wp-promo__video is-%1$s is-youtube" data-forwp-media-lightbox-host><iframe src="%2$s" title="%3$s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>%4$s</figure>',
				esc_attr($video['format']),
				// enablejsapi: the lightbox pauses this player when it opens the video fullscreen.
				esc_url($embed . '&enablejsapi=1'),
				esc_attr($title),
				self::lightbox('youtube', $embed, $title, $video['format'])
			);
		}
		if ($video['url'] !== '') {
			$poster = (string) get_the_post_thumbnail_url($post_id, 'large');
			return sprintf(
				'<figure class="lms4wp-promo__video is-%1$s" data-forwp-media-lightbox-host><video controls playsinline preload="metadata" src="%2$s"%3$s></video>%4$s</figure>',
				esc_attr($video['format']),
				esc_url($video['url']),
				$poster !== '' ? ' poster="' . esc_url($poster) . '"' : '',
				self::lightbox('video', $video['url'], $title, $video['format'])
			);
		}
		return self::renderImage($post_id, $alt_class);
	}

	/**
	 * Featured image with a zoom button (click → lightbox); '' without a featured image.
	 */
	public static function renderImage(int $post_id, string $class = 'lms4wp-promo__image', string $size = 'large', bool $caption = false, array $attr = []): string
	{
		$thumbnail_id = (int) get_post_thumbnail_id($post_id);
		if ($thumbnail_id === 0) {
			return '';
		}
		$image = (string) get_the_post_thumbnail($post_id, $size, ['class' => $class] + $attr);
		if ($image === '') {
			return '';
		}
		$text = $caption ? (string) wp_get_attachment_caption($thumbnail_id) : '';
		$alt = (string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
		return '<figure class="lms4wp-promo__figure" data-forwp-media-lightbox-host>'
			. $image
			. self::lightbox('image', (string) wp_get_attachment_image_url($thumbnail_id, 'full'), $alt !== '' ? $alt : get_the_title($post_id))
			. ($text !== '' ? '<figcaption class="lms4wp-promo__caption">' . esc_html($text) . '</figcaption>' : '')
			. '</figure>';
	}

	/**
	 * Lightbox button that opens the post's video (YouTube or file); '' without a video or 4wp-smart-link.
	 */
	public static function lightboxTrigger(int $post_id, string $label = ''): string
	{
		$video = self::get($post_id);
		$title = get_the_title($post_id);
		$youtube_id = $video['url'] === '' ? self::youtubeId($video['youtube']) : '';
		if ($youtube_id !== '') {
			$src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($youtube_id) . '?rel=0&modestbranding=1&playsinline=1';
			return self::lightbox('youtube', $src, $title, $video['format'], $label);
		}
		return $video['url'] !== '' ? self::lightbox('video', $video['url'], $title, $video['format'], $label) : '';
	}

	/**
	 * Class + style of the media frame: short video gets the blurred featured image as its backdrop
	 * (no black bars next to a 9:16 player).
	 */
	public static function frameAttributes(int $post_id, string $class = 'lms4wp-course-media__frame'): string
	{
		$style = '';
		if (self::hasVideo($post_id)) {
			$format = self::get($post_id)['format'];
			$class .= ' has-video is-' . $format;
			$backdrop = $format === 'short' ? (string) get_the_post_thumbnail_url($post_id, 'medium_large') : '';
			if ($backdrop !== '') {
				$style = '--lms4wp-backdrop:url(' . esc_url($backdrop) . ')';
			}
		}
		return 'class="' . esc_attr($class) . '"' . ($style !== '' ? ' style="' . esc_attr($style) . '"' : '');
	}

	/**
	 * Lightbox button from 4wp-smart-link ('' when the plugin is not active).
	 */
	private static function lightbox(string $type, string $src, string $title, string $format = '', string $label = ''): string
	{
		if ($src === '') {
			return '';
		}
		return (string) apply_filters('forwp_media_lightbox_trigger', '', [
			'type'     => $type,
			'src'      => $src,
			'title'    => $title,
			'format'   => $type === 'image' ? '' : $format,
			'position' => $type === 'image' ? 'top' : 'bottom',
		] + ($label !== '' ? ['label' => $label] : []));
	}

	/**
	 * "Watch on YouTube" link (empty when not set).
	 */
	public static function renderYoutube(int $post_id): string
	{
		$url = self::get($post_id)['youtube'];
		if ($url === '') {
			return '';
		}
		return sprintf(
			'<a class="lms4wp-promo__youtube" href="%s" target="_blank" rel="noopener">%s<span>%s</span></a>',
			esc_url($url),
			Icons::svg('youtube', 18),
			esc_html__('Watch on YouTube', 'lms4wp')
		);
	}

	public static function addMetaBox(): void
	{
		foreach (self::postTypes() as $post_type) {
			add_meta_box('lms4wp_promo_video', __('Promo video', 'lms4wp'), [self::class, 'renderMetaBox'], $post_type, 'side', 'default');
		}
	}

	public static function renderMetaBox(\WP_Post $post): void
	{
		wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
		$video = self::get($post->ID);
		?>
		<p>
			<label for="lms4wp_video_url"><strong><?php esc_html_e('Video file (URL)', 'lms4wp'); ?></strong></label>
			<input type="url" id="lms4wp_video_url" name="lms4wp_video[video]" value="<?php echo esc_attr($video['url']); ?>" style="width:100%" placeholder="https://…/video.mp4">
			<button type="button" class="button lms4wp-video-pick" data-target="lms4wp_video_url" style="margin-top:4px"><?php esc_html_e('Choose from Media Library', 'lms4wp'); ?></button>
		</p>
		<p>
			<label for="lms4wp_video_format"><strong><?php esc_html_e('Format', 'lms4wp'); ?></strong></label>
			<select id="lms4wp_video_format" name="lms4wp_video[video_format]" style="width:100%">
				<option value="short"<?php selected($video['format'], 'short'); ?>><?php esc_html_e('Short (9:16)', 'lms4wp'); ?></option>
				<option value="landscape"<?php selected($video['format'], 'landscape'); ?>><?php esc_html_e('Landscape (16:9)', 'lms4wp'); ?></option>
			</select>
		</p>
		<p>
			<label for="lms4wp_youtube_url"><strong><?php esc_html_e('YouTube URL', 'lms4wp'); ?></strong></label>
			<input type="url" id="lms4wp_youtube_url" name="lms4wp_video[youtube]" value="<?php echo esc_attr($video['youtube']); ?>" style="width:100%" placeholder="https://youtube.com/shorts/…">
		</p>
		<p class="description"><?php esc_html_e('Featured image is used for SEO / previews and as the video poster.', 'lms4wp'); ?></p>
		<?php
	}

	public static function save(int $post_id, \WP_Post $post): void
	{
		if (!in_array($post->post_type, self::postTypes(), true)
			|| !isset($_POST[self::NONCE . '_nonce'], $_POST['lms4wp_video'])
			|| !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE . '_nonce'])), self::NONCE)
			|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			|| !current_user_can('edit_post', $post_id)) {
			return;
		}
		$values = map_deep(wp_unslash((array) $_POST['lms4wp_video']), 'sanitize_text_field');
		foreach (self::metaFrom($values) as $key => $value) {
			if ($value === '') {
				delete_post_meta($post_id, $key);
			} else {
				update_post_meta($post_id, $key, $value);
			}
		}
	}

	public static function enqueue(string $hook): void
	{
		if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
			return;
		}
		$screen = get_current_screen();
		if (!$screen || !in_array($screen->post_type, self::postTypes(), true)) {
			return;
		}
		wp_enqueue_media();
		$js = 'assets/js/promo-video-admin.js';
		wp_enqueue_script(
			self::SCRIPT,
			LMS4WP_URL . $js,
			[],
			file_exists(LMS4WP_PATH . $js) ? (string) filemtime(LMS4WP_PATH . $js) : LMS4WP_VERSION,
			true
		);
	}
}
