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
		$youtube_id = $video['url'] === '' ? self::youtubeId($video['youtube']) : '';
		if ($youtube_id !== '') {
			return sprintf(
				'<figure class="lms4wp-promo__video is-%1$s is-youtube"><iframe src="%2$s" title="%3$s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></figure>',
				esc_attr($video['format']),
				esc_url('https://www.youtube-nocookie.com/embed/' . rawurlencode($youtube_id) . '?rel=0&modestbranding=1&playsinline=1'),
				esc_attr(get_the_title($post_id))
			);
		}
		if ($video['url'] !== '') {
			$poster = (string) get_the_post_thumbnail_url($post_id, 'large');
			return sprintf(
				'<figure class="lms4wp-promo__video is-%1$s"><video controls playsinline preload="metadata" src="%2$s"%3$s></video></figure>',
				esc_attr($video['format']),
				esc_url($video['url']),
				$poster !== '' ? ' poster="' . esc_url($poster) . '"' : ''
			);
		}
		return has_post_thumbnail($post_id)
			? (string) get_the_post_thumbnail($post_id, 'large', ['class' => $alt_class])
			: '';
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
