<?php
if ( ! defined('ABSPATH') ) {
	exit;
}

if ( ! class_exists('SwellChild_ACF_Article_Generator') ) {
	class SwellChild_ACF_Article_Generator {

		/**
		 * Render the article content by replacing tokens with ACF/meta values.
		 *
		 * @param int         $post_id  Post ID.
		 * @param string|null $template Optional template string.
		 * @return string
		 */
                public static function render_from_acf($post_id, $template = null) {
                        $post_id  = absint($post_id);
                        $template = $template ?: self::default_template();

                        if ( ! $post_id || ! $template ) {
                                return '';
                        }

                        $tokens                 = self::collect_tokens($post_id);
                        $content                = $template;
                        $raw_html_tokens        = ['image_gallery_block', 'image_gallery', 'image_img', 'tracks', 'sample'];
                        $has_gallery_placeholder = (strpos($template, '{{image_gallery_block}}') !== false);

                        foreach ( $tokens as $token => $value ) {
                                if ( is_array($value) || is_object($value) ) {
                                        $value = '';
                                }

                                $placeholder = '{{' . $token . '}}';

                                if ( in_array($token, $raw_html_tokens, true) ) {
                                        $content = str_replace($placeholder, (string) $value, $content);
                                } else {
                                        $content = str_replace($placeholder, esc_html((string) $value), $content);
                                }
                        }

                        // 未解決プレースホルダと空タグ、過剰な改行を掃除
                        $content = preg_replace('/{{[^}]+}}/', '', $content);
                        $content = preg_replace('/<([a-z0-9]+)([^>]*)>\s*<\/\1>/', '', $content);
                        $content = preg_replace('/\n{3,}/', "\n\n", $content);

                        // Auto-prepend the gallery block at top if the template doesn't include it
                        if ( ! empty($tokens['image_gallery_block']) ) {
                                $already_present = (strpos($content, 'wp-block-gallery') !== false);
                                if ( ! $has_gallery_placeholder && ! $already_present ) {
                                        $content = $tokens['image_gallery_block'] . "\n" . $content;
                                }
                        }

                        if ( self::is_admin_user() ) {
                                $diag = [
                                        'circle'             => $tokens['circle']      ?? '',
                                        'circle_name'        => $tokens['circle_name'] ?? '',
                                        'found_from'         => $tokens['__circle_found_from'] ?? '',
                                        'found_image_field'  => $tokens['__image_found_from'] ?? '',
                                        'first_image'        => $tokens['image_url'] ?? '',
                                        'post_id'            => (string) $post_id,
                                        'title'              => (string) get_the_title($post_id),
                                ];
                                $content .= "\n<!-- ACFGEN DEBUG " . wp_json_encode($diag, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . " -->";
                        }

                        return trim($content);
                }

                /** Safe ACF getter */
                private static function get_acf( $key, $post_id ) {
                        if ( function_exists('get_field') ) {
                                $v = get_field( $key, $post_id );
                                if ( $v !== null && $v !== '' ) return $v;
                        }
                        return get_post_meta( $post_id, $key, true );
                }

                /** Normalize an ACF image-like value (array/ID/URL) to URL */
                private static function image_like_to_url( $v ) {
                        if ( is_array($v) ) {
                                if ( !empty($v['url']) ) return esc_url_raw($v['url']);
                                if ( !empty($v['ID']) || !empty($v['id']) ) {
                                        $id = absint( $v['ID'] ?? $v['id'] );
                                        $u  = wp_get_attachment_image_url( $id, 'full' );
                                        return $u ? esc_url_raw($u) : '';
                                }
                        }
                        if ( is_numeric($v) ) {
                                $u = wp_get_attachment_image_url( (int)$v, 'full' );
                                return $u ? esc_url_raw($u) : '';
                        }
                        if ( is_string($v) && preg_match('~^https?://~', $v) ) {
                                return esc_url_raw($v);
                        }
                        return '';
                }

                /** Extract image URLs from legacy textarea text */
                private static function extract_image_urls_from_text( $text ) {
                        if ( ! is_string($text) || $text === '' ) return [];
                        preg_match_all( '~https?://[^\s"<>\)\(]+~i', $text, $m );
                        $urls = array_map('trim', $m[0] ?? []);
                        if ( ! $urls ) {
                                $parts = preg_split('/[\r\n,]+/', $text);
                                $urls = array_map('trim', (array)$parts);
                        }
                        $valid = [];
                        foreach ( $urls as $u ) {
                                $u = rtrim($u, '.,);\'"');
                                if ( $u !== '' && preg_match('~\.(jpe?g|png|gif|webp|avif)(\?.*)?$~i', $u) ) {
                                        $valid[] = esc_url_raw($u);
                                }
                        }
                        return array_values(array_unique($valid));
                }

                /** Collect gallery URLs from multiple possible ACF shapes/keys */
                private static function resolve_gallery_urls_with_source( $post_id ) {
                        $candidates = [
                                'gallery_images',        // expected field name
                                'gallery', 'images',
                                'image', 'main_image', 'cover_image',
                                'image_acquired','image_get','image_kakutoku', // e.g. 「画像取得」系のスラッグ想定
                        ];

                        $found = ''; $urls = [];
                        foreach ( $candidates as $key ) {
                                $val = self::get_acf( $key, $post_id );
                                if ( $val === null || $val === '' ) continue;

                                if ( is_array($val) ) {
                                        $maybe = $val;
                                        // Repeater: [ ['image'=>...], ... ]
                                        if ( isset($val[0]) && is_array($val[0]) && array_key_exists('image', $val[0]) ) {
                                                $maybe = array_map( fn($row) => $row['image'] ?? null, $val );
                                        }
                                        foreach ( (array)$maybe as $it ) {
                                                $u = self::image_like_to_url( $it );
                                                if ( $u ) $urls[] = $u;
                                        }
                                } elseif ( is_numeric($val) || (is_string($val) && preg_match('~^https?://~',$val)) ) {
                                        $u = self::image_like_to_url( $val );
                                        if ( $u ) $urls[] = $u;
                                } elseif ( is_string($val) ) {
                                        $urls = array_merge( $urls, self::extract_image_urls_from_text( $val ) );
                                }
                                if ( $urls ) { $found = $key; break; }
                        }
                        return [ array_values(array_unique($urls)), $found ];
                }

                /** Build Gutenberg block HTML for a gallery like the user's example */
                private static function build_wp_gallery_block_html( array $urls, $columns = 7 ) {
                        if ( empty($urls) ) return '';
                        $cols = max(1, (int)$columns);

                        $block  = '<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->' . "\n";
                        $block .= '<div class="wp-block-group">' . "\n";
                        $block .= '<!-- wp:gallery {"columns":' . $cols . ',"linkTo":"none"} -->' . "\n";
                        $block .= '<figure class="wp-block-gallery has-nested-images columns-' . $cols . ' is-cropped">' . "\n";

                        foreach ( $urls as $idx => $u ) {
                                $size = ($idx === 0) ? 'large' : 'full'; // 例に合わせて最初だけ large
                                $block .= '<!-- wp:image {"sizeSlug":"' . $size . '","linkDestination":"none"} -->' . "\n";
                                $block .= '<figure class="wp-block-image size-' . $size . '"><img src="' . esc_url($u) . '" alt="" /></figure>' . "\n";
                                $block .= '<!-- /wp:image -->' . "\n";
                        }

                        $block .= '</figure>' . "\n";
                        $block .= '<!-- /wp:gallery -->' . "\n";
                        $block .= '</div>' . "\n";
                        $block .= '<!-- /wp:group -->';

                        return $block;
                }

                /** Build HTML gallery block from URL array */
                private static function build_image_gallery_html( array $urls, $post_id ) {
                        if ( empty( $urls ) ) return '';
                        $out = '<div class="acfgen-gallery">';
                        $alt = esc_attr( get_the_title( $post_id ) );
                        foreach ( $urls as $u ) {
                                $out .= '<img src="' . esc_url( $u ) . '" alt="' . $alt . '" loading="lazy" decoding="async" />';
                        }
                        $out .= '</div>';
                        return $out;
                }

                private static function terms_list($post_id, $taxonomy) {
                        $terms = get_the_terms($post_id, $taxonomy);
                        if ( ! $terms || is_wp_error($terms) ) {
                                return '';
                        }
                        $names = wp_list_pluck($terms, 'name');
                        if ( ! $names ) {
                                return '';
                        }
                        return implode(', ', $names);
                }

                /**
                 * Resolve an image URL for the post from various sources:
                 * - ACF image fields (ID/array/URL)
                 * - ACF gallery (first image)
                 * - Featured image (thumbnail)
                 */
                private static function resolve_image_url( $post_id ) {
                        // 1) ACF image-like fields (broadened keys for compatibility)
                        $acfs = ['image', 'main_image', 'cover_image', 'image_url'];
                        foreach ( $acfs as $key ) {
                                $v = method_exists(__CLASS__, 'get_acf') ? self::get_acf($key, $post_id) : get_post_meta($post_id, $key, true);
                                if ( empty($v) ) continue;

                                // ACF "Image" returns array (url / id / ID)
                                if ( is_array($v) ) {
                                        if ( !empty($v['url']) ) return esc_url( $v['url'] );
                                        if ( !empty($v['ID']) || !empty($v['id']) ) {
                                                $id  = absint( $v['ID'] ?? $v['id'] );
                                                $url = wp_get_attachment_image_url( $id, 'full' );
                                                if ( $url ) return esc_url( $url );
                                        }
                                }

                                // Image ID only
                                if ( is_numeric($v) ) {
                                        $url = wp_get_attachment_image_url( (int) $v, 'full' );
                                        if ( $url ) return esc_url( $url );
                                }

                                // Direct URL
                                if ( is_string($v) && preg_match( '~^https?://~', $v ) ) {
                                        return esc_url( $v );
                                }
                        }

                        // 2) ACF Gallery (first item)
                        $gallery_keys = ['gallery_images', 'gallery', 'images'];
                        foreach ( $gallery_keys as $gk ) {
                                $g = method_exists(__CLASS__, 'get_acf') ? self::get_acf($gk, $post_id) : get_post_meta($post_id, $gk, true);
                                if ( $g && is_array($g) ) {
                                        $first = reset($g);

                                        // Gallery element can be array or ID
                                        if ( is_array($first) ) {
                                                if ( !empty($first['url']) ) return esc_url( $first['url'] );
                                                if ( !empty($first['ID']) || !empty($first['id']) ) {
                                                        $id  = absint( $first['ID'] ?? $first['id'] );
                                                        $url = wp_get_attachment_image_url( $id, 'full' );
                                                        if ( $url ) return esc_url( $url );
                                                }
                                        } elseif ( is_numeric($first) ) {
                                                $url = wp_get_attachment_image_url( (int) $first, 'full' );
                                                if ( $url ) return esc_url( $url );
                                        }
                                }
                        }

                        // 3) Featured image
                        $thumb_id = get_post_thumbnail_id( $post_id );
                        if ( $thumb_id ) {
                                $url = wp_get_attachment_image_url( $thumb_id, 'full' );
                                if ( $url ) return esc_url( $url );
                        }

                        return '';
                }

                /** Admin-only? */
                private static function is_admin_user() {
                        return function_exists('current_user_can') && current_user_can('manage_options');
                }

                /** Normalize key for loose matching */
                private static function norm_key( $k ) {
                        return strtolower( trim( preg_replace('/\s+/', '_', (string)$k) ) );
                }

                /** Recursively search an arbitrary array for keys that look like circle_name / circle */
                private static function deep_find_circle_like( $data ) {
                        $targets = ['circle_name', 'circle'];
                        if ( is_array($data) ) {
                                foreach ( $data as $k => $v ) {
                                        $nk = self::norm_key($k);
                                        foreach ( $targets as $t ) {
                                                if ( $nk === $t || strpos($nk, $t) !== false ) {
                                                        // leaf candidate
                                                        if ( is_string($v) ) {
                                                                $val = trim($v);
                                                                if ( $val !== '' ) return $val;
                                                        }
                                                }
                                        }
                                        // descend
                                        if ( is_array($v) ) {
                                                $hit = self::deep_find_circle_like($v);
                                                if ( $hit !== '' ) return $hit;
                                        }
                                }
                        }
                        return '';
                }

                /**
                 * Smart resolver for circle name:
                 *  - try direct 'circle_name'
                 *  - try deep scan of all ACF fields (groups/flexible)
                 *  - try raw post meta keys that contain 'circle'
                 *  - try linked post IDs (linked_review / review_post / related_post), recurse once
                 */
                private static function resolve_circle_name_smart( $post_id, &$found_from = null, $depth = 0 ) {
                        $found_from = $found_from ?? '';

                        // 1) Direct ACF text: 'circle_name' (exact)
                        $direct = self::get_acf('circle_name', $post_id);
                        if ( is_string($direct) && trim($direct) !== '' ) {
                                $found_from = 'acf:circle_name';
                                return trim($direct);
                        }

                        // 2) Deep scan all ACF fields (group/flexible)
                        $all = function_exists('get_fields') ? get_fields($post_id) : [];
                        if ( is_array($all) && $all ) {
                                $hit = self::deep_find_circle_like($all);
                                if ( $hit !== '' ) {
                                        $found_from = 'acf:deep';
                                        return $hit;
                                }
                        }

                        // 3) Raw meta scan (keys containing 'circle')
                        $meta = get_post_meta($post_id);
                        if ( is_array($meta) ) {
                                foreach ( $meta as $k => $vals ) {
                                        if ( strpos(self::norm_key($k), 'circle') === false ) continue;
                                        $val = '';
                                        if ( is_array($vals) ) {
                                                foreach ( $vals as $vv ) {
                                                        if ( is_string($vv) && trim($vv) !== '' ) { $val = trim($vv); break; }
                                                }
                                        } elseif ( is_string($vals) ) {
                                                $val = trim($vals);
                                        }
                                        if ( $val !== '' ) {
                                                $found_from = 'meta:' . $k;
                                                return $val;
                                        }
                                }
                        }

                        // 4) Linked post fallback (recurse once to avoid infinite loops)
                        if ( $depth === 0 ) {
                                $link_keys = ['linked_review', 'review_post', 'related_post', 'linked_post'];
                                foreach ( $link_keys as $lk ) {
                                        $lp = self::get_acf($lk, $post_id);
                                        $link_id = 0;
                                        // ACF link may be ID or array or array-of
                                        if ( is_numeric($lp) ) $link_id = (int)$lp;
                                        elseif ( is_array($lp) ) {
                                                if ( isset($lp['ID']) || isset($lp['id']) ) $link_id = (int)($lp['ID'] ?? $lp['id']);
                                                elseif ( isset($lp[0]) && is_numeric($lp[0]) ) $link_id = (int)$lp[0];
                                        }
                                        if ( $link_id > 0 ) {
                                                $ff = '';
                                                $found = self::resolve_circle_name_smart($link_id, $ff, $depth+1);
                                                if ( $found !== '' ) {
                                                        $found_from = 'linked:' . $lk . '→' . ($ff ?? '');
                                                        return $found;
                                                }
                                        }
                                }
                        }

                        return '';
                }

                private static function collect_tokens($post_id){
                        $tokens = [];
                        $tokens['title']      = get_the_title($post_id);
                        $tokens['excerpt']    = get_post_field('post_excerpt', $post_id);
			$tokens['permalink']  = get_permalink($post_id);

			// ACF/meta (Phase 1 mapping to current keys)
			$tokens['dlsite_url']   = esc_url(self::get_acf('dlsite_url', $post_id));
			// gallery_images は Phase 1 では未使用
			$tokens['sample']       = self::get_acf('sample', $post_id);

                        $tokens['voice_actors'] = self::get_acf('voice_actors_text', $post_id);
			$tokens['illustrators'] = self::get_acf('illustrators_text', $post_id);

			$tokens['price_regular']= self::get_acf('price_regular_text', $post_id);
			$tokens['price_sale']   = self::get_acf('price_sale_text', $post_id);
			$tokens['sale_end']     = self::get_acf('sale_ends_text', $post_id);

			$tokens['release_date'] = self::get_acf('release_text', $post_id);
			$tokens['genres']       = self::get_acf('genres_text', $post_id);

			// Tracks: comma or newline separated → ordered list
			$tracks_raw = self::get_acf('tracks', $post_id);
			$tokens['tracks'] = '';
			if ($tracks_raw){
				$lines = array_filter(array_map('trim', preg_split('/[,\n]+/', (string)$tracks_raw)));
				if ($lines){
					$lis = '';
					foreach ($lines as $i => $t){
						$lis .= '<li>' . esc_html(($i+1).'. '.$t) . '</li>';
					}
					$tokens['tracks'] = '<ol>'.$lis.'</ol>';
				}
			}

                        // Taxonomies（表示用）
                        $tokens['categories'] = self::terms_list($post_id, 'category');
                        $tokens['tags']       = self::terms_list($post_id, 'post_tag');

                        // --- Image tokens ---
                        $image_url = self::resolve_image_url( $post_id );
                        $tokens['image_url'] = $image_url;
                        $tokens['image_img'] = $image_url
                                ? '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" loading="lazy" decoding="async" />'
                                : '';

                        // --- Build Gutenberg gallery block from ACF images ---
                        list($__urls, $__img_found_key) = self::resolve_gallery_urls_with_source( $post_id );

                        $tokens['image_urls_json']     = $__urls ? wp_json_encode($__urls, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : '[]';
                        $tokens['image_gallery_block'] = $__urls ? self::build_wp_gallery_block_html($__urls, 7) : '';
                        $tokens['image_gallery']       = $__urls ? self::build_image_gallery_html($__urls, $post_id) : '';

                        // 1枚目も欲しい場面用に（任意）
                        $tokens['image_url'] = $__urls[0] ?? ($tokens['image_url'] ?? '');
                        $tokens['image_img'] = !empty($__urls)
                                ? '<img src="'.esc_url($__urls[0]).'" alt="'.esc_attr(get_the_title($post_id)).'" loading="lazy" decoding="async" />'
                                : ($tokens['image_img'] ?? '');

                        // debug: which field used
                        $tokens['__image_found_from'] = $__img_found_key;

                        // --- Circle name (smart) ---
                        $__from = '';
                        $__circle = self::resolve_circle_name_smart( $post_id, $__from );
                        if ( $__circle !== '' ) {
                                $tokens['circle_name'] = $tokens['circle_name'] ?? $__circle;
                                $tokens['circle']      = $tokens['circle']      ?? $__circle;
                        }
                        $tokens['__circle_found_from'] = $__from; // debug marker

                        // --- Token aliases for backward/forward compatibility ---
                        if ( isset( $tokens['circle'] ) && $tokens['circle'] !== '' ) {
                                $tokens['circle_name'] = $tokens['circle'];
                        } elseif ( isset( $tokens['circle_name'] ) && $tokens['circle_name'] !== '' ) {
                                $tokens['circle'] = $tokens['circle_name'];
                        }

                        return $tokens;
                }

		/* ====== Phase1 wiring: hooks, UI, AJAX, settings ====== */

		const OPTION_KEY = 'sora_acf_article_template';
		const NONCE_KEY  = 'sora_acf_generate_nonce';

		public static function boot() {
			add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
			add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
			add_action('wp_ajax_sora_generate_from_acf', [__CLASS__, 'ajax_generate']);
			add_action('save_post', [__CLASS__, 'autofill_when_empty'], 20, 2);

			// 設定画面（テンプレ編集）
			add_action('admin_menu', [__CLASS__, 'register_settings_page']);
			add_action('admin_init', [__CLASS__, 'register_settings']);
		}

		public static function add_metabox() {
			$screens = ['post']; // 必要なら 'short_videos' などを追加
			foreach ($screens as $screen) {
				add_meta_box(
					'sora-acf-generate-box',
					'ACFから本文を自動生成',
					[__CLASS__, 'render_metabox'],
					$screen,
					'side',
					'high'
				);
			}
		}

		public static function render_metabox($post) {
			$nonce = wp_create_nonce(self::NONCE_KEY);
			?>
			<div>
				<p>現在のACFの値をテンプレに差し込んで本文を生成します。</p>
				<label style="display:block;margin:8px 0;">
					<input type="checkbox" id="sora-acf-overwrite" /> 既存の本文を上書きする
				</label>
				<button type="button" class="button button-primary" id="sora-acf-generate-btn">生成して本文に反映</button>
				<div id="sora-acf-generate-msg" style="margin-top:8px;color:#2271b1;"></div>
			</div>
			<script>
			window.SORA_ACF_GENERATE = {
				ajaxUrl: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>",
				postId: <?php echo (int) $post->ID; ?>,
				nonce: "<?php echo esc_js($nonce); ?>"
			};
			</script>
			<?php
		}

		public static function enqueue_admin($hook) {
			if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
			// ★ 明示的に jQuery を読み込んでからインラインJSを差す
			wp_enqueue_script('jquery');
			wp_add_inline_script('jquery', self::inline_js());
		}

		private static function inline_js() {
			return <<<JS
jQuery(document).on('click', '#sora-acf-generate-btn', function(){
	const msg = jQuery('#sora-acf-generate-msg');
	msg.css('color','#2271b1').text('生成中...');
	jQuery.post(window.SORA_ACF_GENERATE.ajaxUrl, {
		action: 'sora_generate_from_acf',
		_nonce: window.SORA_ACF_GENERATE.nonce,
		post_id: window.SORA_ACF_GENERATE.postId,
		overwrite: jQuery('#sora-acf-overwrite').is(':checked') ? 1 : 0
	}, function(res){
		if (res && res.success) {
			msg.text('本文を反映しました。保存してください。');
			if (window.wp && wp.data && wp.data.dispatch) {
				try { wp.data.dispatch('core/editor').editPost({ content: res.data.content }); } catch(e){}
			}
		} else {
			const m = res && res.data && res.data.message ? res.data.message : '失敗しました';
			msg.css('color','#d63638').text(m);
		}
	});
});
JS;
		}

		public static function ajax_generate() {
			if (!current_user_can('edit_posts')) {
				wp_send_json_error(['message' => '権限がありません']);
			}
			check_ajax_referer(self::NONCE_KEY, '_nonce');

			$post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
			$overwrite = !empty($_POST['overwrite']);
			if (!$post_id) wp_send_json_error(['message' => 'post_idが不正']);

			$post = get_post($post_id);
			if (!$post) wp_send_json_error(['message' => '投稿が見つかりません']);

			// 設定のテンプレが空ならデフォルトにフォールバック
			$tpl = get_option(self::OPTION_KEY, self::default_template());
			if (!is_string($tpl) || trim($tpl) === '') {
				$tpl = self::default_template();
			}

			$content = self::render_from_acf($post_id, $tpl);
			if ($content === '') {
				wp_send_json_error(['message' => 'テンプレートまたはデータが空です']);
			}

			if ($overwrite || trim(wp_strip_all_tags($post->post_content)) === '') {
				wp_update_post(['ID' => $post_id, 'post_content' => $content]);
			}

			wp_send_json_success(['content' => $content]);
		}

		public static function autofill_when_empty($post_id, $post) {
			if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
			if ($post->post_type !== 'post') return;

			// 空白やゴミブロックのみも「空」と判定
			$raw = (string) $post->post_content;
			$stripped = trim(wp_strip_all_tags($raw));
			if ($stripped !== '') return;

			$tpl = get_option(self::OPTION_KEY, self::default_template());
			if (!is_string($tpl) || trim($tpl) === '') {
				$tpl = self::default_template();
			}

			$content = self::render_from_acf($post_id, $tpl);
			if ($content) {
				remove_action('save_post', [__CLASS__, 'autofill_when_empty'], 20);
				wp_update_post(['ID' => $post_id, 'post_content' => $content]);
				add_action('save_post', [__CLASS__, 'autofill_when_empty'], 20, 2);
			}
		}

		/* ====== Settings page for template ====== */
		public static function register_settings_page() {
			add_options_page(
				'ACF記事テンプレート',
				'ACF記事テンプレート',
				'manage_options',
				'sora-acf-template',
				[__CLASS__, 'render_settings_page']
			);
		}

		public static function register_settings() {
			register_setting('sora_acf_template_group', self::OPTION_KEY);
			add_settings_section(
				'sora_acf_template_section',
				'テンプレート',
				function () {
					echo '<p>本文テンプレート。{{title}} などのプレースホルダを使用できます。</p>';
				},
				'sora-acf-template'
			);
			add_settings_field(
				'sora_acf_template_field',
				'本文テンプレート',
				[__CLASS__, 'render_template_field'],
				'sora-acf-template',
				'sora_acf_template_section'
			);
		}

		public static function render_template_field() {
			$value = get_option(self::OPTION_KEY, self::default_template());
			echo '<textarea name="' . esc_attr(self::OPTION_KEY) . '" rows="24" style="width:100%;font-family:monospace;">' . esc_textarea($value) . '</textarea>';
		}

		public static function render_settings_page() {
			echo '<div class="wrap"><h1>ACF記事テンプレート</h1><form method="post" action="options.php">';
			settings_fields('sora_acf_template_group');
			do_settings_sections('sora-acf-template');
			submit_button('保存');
			echo '</form></div>';
		}

                public static function default_template(){
                        return <<<HTML
<h2>{{title}}</h2>
{{image_gallery}}
<p>記録：本件は観察対象の音声作品。主観は排除し、事実のみ列挙する。</p>

<h3>基本情報</h3>
<ul>
	<li>サークル：{{circle}}</li>
	<li>声優：{{voice_actors}}</li>
	<li>イラスト：{{illustrators}}</li>
	<li>ジャンル：{{genres}}</li>
	<li>発売日：{{release_date}}</li>
	<li>カテゴリ：{{categories}}</li>
	<li>タグ：{{tags}}</li>
</ul>

<h3>価格</h3>
<ul>
	<li>通常価格：{{price_regular}}</li>
	<li>セール価格：{{price_sale}}</li>
	<li>セール終了日：{{sale_end}}</li>
</ul>

<h3>サンプル</h3>
<div class="preview">{{sample}}</div>

<h3>トラック一覧</h3>
{{tracks}}

<h3>購入</h3>
<p><a href="{{dlsite_url}}" target="_blank" rel="noopener">DLsiteで確認する</a></p>

<hr />
<p>以上。必要十分な情報のみを残した。</p>
HTML;
		}
	}
}

SwellChild_ACF_Article_Generator::boot();