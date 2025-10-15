<?php
/* 子テーマ functions.php */

require_once get_stylesheet_directory() . '/inc/enqueue.php';
require_once get_stylesheet_directory() . '/inc/hooks.php';

add_filter('body_class', function ($classes) {
  if (is_admin()) {
    return $classes;
  }

  if (!in_array('has-bottom-nav', $classes, true)) {
    $classes[] = 'has-bottom-nav';
  }

  return $classes;
});

add_filter('body_class', function ($classes) {
  $tpl = get_page_template_slug();
  if (is_front_page() || ($tpl && basename($tpl) === 'page-filter.php')) {
    $classes[] = 'is-search-page';
  }
  return $classes;
});

add_filter('body_class', function ($classes) {
  $tpl_slug = get_page_template_slug();
  if (!$tpl_slug && is_page()) {
    $tpl_slug = get_page_template_slug(get_queried_object_id());
  }

  if ($tpl_slug && basename($tpl_slug) === 'template-discover.php') {
    foreach (['is-discover-page', 'shorts-mode'] as $class) {
      if (!in_array($class, $classes, true)) {
        $classes[] = $class;
      }
    }
  }

  return $classes;
});
/** -------------------------------------------
 * Ajax: レビュー本文を返す（ボトムシート用）
 * フロント：action=sora_get_review, post_id=...
 * 返却: { success: true, data: { title, html, permalink } }
 * ------------------------------------------- */
add_action('wp_ajax_sora_get_review',    'sora_get_review');
add_action('wp_ajax_nopriv_sora_get_review', 'sora_get_review');

function sora_get_review() {
  // 1) Nonceチェック
  if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'sora_sheet') ) {
    wp_send_json_error(['message' => 'Invalid nonce'], 403);
  }

  // 2) post_id 取得
  $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
  if ( ! $post_id ) {
    wp_send_json_error(['message' => 'Invalid post_id'], 400);
  }

  // 3) 対象ポスト解決（short_videos → 紐付けレビューへフォールバック）
  $target = get_post($post_id);
  if ( ! $target || 'publish' !== $target->post_status ) {
    wp_send_json_error(['message' => 'Post not found'], 404);
  }

  // もし short_videos の場合は、ACFなどで紐付けられたレビュー記事を優先
  if ( 'short_videos' === $target->post_type ) {
    // 例: ACF フィールド 'review_post' / メタ 'linked_review_post_id' などに対応
    $linked_id = 0;

    // ACF（存在すれば）
    if ( function_exists('get_field') ) {
      $acf_link = get_field('review_post', $post_id);
      if ( is_numeric($acf_link) ) {
        $linked_id = absint($acf_link);
      } elseif ( is_array($acf_link) && ! empty($acf_link['ID']) ) {
        $linked_id = absint($acf_link['ID']);
      }
    }

    // カスタムメタ（数値ID）でも対応
    if ( ! $linked_id ) {
      $meta_link = get_post_meta($post_id, 'linked_review_post_id', true);
      if ( $meta_link ) $linked_id = absint($meta_link);
    }

    // 紐付けが有効なら置き換え
    if ( $linked_id ) {
      $linked = get_post($linked_id);
      if ( $linked && 'publish' === $linked->post_status ) {
        $target = $linked;
      }
    }
  }

  // 4) 本文生成（the_content 経由でショートコード等も展開）
  $title   = get_the_title($target);
  $content = apply_filters('the_content', $target->post_content);

  // 必要に応じて許可タグでサニタイズ（「投稿と同等」に揃える）
  $allowed = wp_kses_allowed_html('post');
  $html    = wp_kses($content, $allowed);

  // 5) パーマリンク
  $permalink = get_permalink($target);

  // 6) 返却
  wp_send_json_success([
    'title'     => $title,
    'html'      => $html,
    'permalink' => $permalink,
  ]);
}

/**
 * BottomSheet: return linked review post HTML
 * POST: post_id (short_videos ID), _ajax_nonce
 * Response: JSON { ok: true, html: "..." }
 */
add_action('wp_ajax_sora_get_linked_review', 'sora_get_linked_review');
add_action('wp_ajax_nopriv_sora_get_linked_review', 'sora_get_linked_review');
function sora_get_linked_review() {
  if ( empty($_POST['_ajax_nonce']) || ! wp_verify_nonce($_POST['_ajax_nonce'], 'sora_bs_nonce') ) {
    wp_send_json_error(['message' => 'Invalid nonce.']);
  }

  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  if ( ! $post_id ) wp_send_json_error(['message' => 'Invalid post_id.']);

  $linked = function_exists('get_field') ? get_field('linked_review', $post_id) : null;
  $review_id = 0;
  if (is_array($linked) && isset($linked['ID'])) {
    $review_id = intval($linked['ID']);
  } elseif (is_object($linked) && isset($linked->ID)) {
    $review_id = intval($linked->ID);
  } elseif (is_numeric($linked)) {
    $review_id = intval($linked);
  }

  if ( ! $review_id || get_post_status($review_id) !== 'publish' ) {
    wp_send_json_error(['message' => 'Review not found.']);
  }

  $title   = get_the_title($review_id);
  $content = apply_filters('the_content', get_post_field('post_content', $review_id));
  $thumb   = get_the_post_thumbnail($review_id, 'large', ['loading' => 'lazy', 'decoding' => 'async']);
  $perma   = get_permalink($review_id);

  ob_start(); ?>
    <article class="sora-review-article" data-review-id="<?php echo esc_attr($review_id); ?>">
      <header class="sora-review-header">
        <h2 class="sora-review-title"><?php echo esc_html($title); ?></h2>
        <?php if ($thumb) : ?>
          <div class="sora-review-thumb"><?php echo $thumb; ?></div>
        <?php endif; ?>
      </header>
      <div class="sora-review-content">
        <?php echo $content; ?>
      </div>
      <footer class="sora-review-footer">
        <a class="sora-review-permalink" href="<?php echo esc_url($perma); ?>">Open full article</a>
      </footer>
    </article>
  <?php
  $html = ob_get_clean();

  wp_send_json_success(['html' => $html]);
}

// Inject the review bottom-sheet markup once in the footer
add_action('wp_footer', function () {
  if (is_admin()) {
    return;
  }
  $tpl_slug = get_page_template_slug();
  if (!$tpl_slug && is_page()) {
    $tpl_slug = get_page_template_slug(get_queried_object_id());
  }

  $is_discover_tpl = $tpl_slug && basename($tpl_slug) === 'template-discover.php';

  if (! (is_singular('short_videos') || (is_page() && $is_discover_tpl))) {
    return;
  }
  static $printed = false;
  if ($printed) {
    return;
  }
  $printed = true;
  ?>
  <div class="bs-overlay" id="sora-review-overlay" aria-hidden="true"></div>
  <aside class="bs-sheet" id="sora-review-sheet" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1">
    <div class="bs-handle" aria-hidden="true"></div>
    <header class="bs-header">
      <h3 class="bs-title" id="sora-bs-title">レビュー</h3>
      <button class="bs-close" type="button" aria-label="閉じる">✕</button>
    </header>
    <div class="bs-body" id="sora-bs-content"></div>
  </aside>
  <?php
}, 99);

/* =====================================
 * ランダム記事へのリダイレクト処理
 * =====================================
 * URLに `?random-post` が付いているときに、
 * 投稿タイプ 'post' の中からランダムに1件選んでリダイレクトする。
 * オプションで category, tag を指定して絞り込み可能。
 */
if (!function_exists('redirect_random_post')) {
  function redirect_random_post() {
    if (isset($_GET['random-post'])) {
      $base_args = [
        'post_type'      => 'post',
        'posts_per_page' => 1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
      ];

      // カテゴリーで絞り込み（カンマ区切り）
      if (isset($_GET['category'])) {
        $base_args['category__in'] = array_map('intval', explode(',', $_GET['category']));
      }

      // タグで絞り込み（カンマ区切り）
      if (isset($_GET['tag'])) {
        $base_args['tag__in'] = explode(',', $_GET['tag']);
      }

      // 件数取得用クエリ
      $count_query = new WP_Query(array_merge($base_args, [
        'no_found_rows' => false,
        'fields'        => 'ids',
      ]));

      $count = (int) $count_query->found_posts;

      if ($count > 0) {
        $offset = $count > 1 ? wp_rand(0, $count - 1) : 0;
        $random_query = new WP_Query(array_merge($base_args, [
          'offset'        => $offset,
          'no_found_rows' => true,
        ]));

        if ($random_query->have_posts()) {
          wp_redirect(get_permalink($random_query->posts[0]->ID));
        } else {
          wp_redirect(home_url());
        }
      } else {
        wp_redirect(home_url());
      }
      exit;
    }
  }
  add_action('template_redirect', 'redirect_random_post');
}

/* =====================================
 * ACF レビュー出力を記事本文の前に挿入
 * =====================================
 * シングル投稿ページで、テンプレートパーツ
 * `template-parts/sora-review.php` を the_content の前に表示。
 */
function sora_display_acf_review($content) {
  if (is_singular('post') && in_the_loop() && is_main_query()) {
    ob_start();
    get_template_part('template-parts/sora-review'); // ACF レビューのテンプレートを読み込み
    $acf_output = ob_get_clean();
    return $acf_output . $content; // 本文の前に挿入
  }
  return $content;
}
add_filter('the_content', 'sora_display_acf_review');

/**
 * Sanitize all values in an array recursively.
 *
 * @param array $arr
 * @return array
 */
function sanitize_array(array $arr){
  foreach($arr as $k=>$v){
    $arr[$k]=is_array($v)?sanitize_array($v):sanitize_text_field($v);
  }
  return $arr;
}


/**
 * Build WP_Query arguments based on provided filter parameters.
 *
 * @param array $params    Sanitized request parameters.
 * @param array $override  Additional/override arguments for the query.
 * @return array
 */
function build_filter_query_args(array $params, array $override = []) {
  $args = [
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'ignore_sticky_posts'    => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
    'no_found_rows'          => true,
    'tax_query'              => [],
    'meta_query'             => [],
  ];

  // Pagination and per page settings can be overridden
  if (isset($override['posts_per_page'])) {
    $args['posts_per_page'] = (int) $override['posts_per_page'];
  }
  if (isset($override['paged'])) {
    $args['paged'] = (int) $override['paged'];
  }

  // Taxonomy filters
  $taxonomies = ['category', 'post_tag', 'voice_pitch', 'level'];
  foreach ($taxonomies as $tax) {
    if (!empty($params[$tax])) {
      $args['tax_query'][] = [
        'taxonomy' => $tax,
        'field'    => 'slug',
        'terms'    => (array) $params[$tax],
        'operator' => 'IN',
      ];
    }
  }

  // Slider based meta filters
  $sliders = ['price', 'ejaculation_count', 'instruction', 'guide', 'countdown_shot', 'edging_count'];
  foreach ($sliders as $key) {
    $min = isset($params[$key . '_min']) ? (int) $params[$key . '_min'] : null;
    $max = isset($params[$key . '_max']) ? (int) $params[$key . '_max'] : null;

    if ($min !== null || $max !== null) {
      $range = [
        'key'  => $key,
        'type' => 'NUMERIC',
      ];

      if ($min !== null && $max !== null) {
        $range['value']   = [$min, $max];
        $range['compare'] = 'BETWEEN';
      } elseif ($min !== null) {
        $range['value']   = $min;
        $range['compare'] = '>=';
      } else {
        $range['value']   = $max;
        $range['compare'] = '<=';
      }

      $args['meta_query'][] = $range;
    }
  }

  if (count($args['meta_query']) > 1) {
    $args['meta_query']['relation'] = 'AND';
  }
  if (count($args['tax_query']) > 1) {
    $args['tax_query']['relation'] = 'AND';
  }

  // Sort options
  if (!empty($params['sort'])) {
    $sort = sanitize_text_field($params['sort']);
    switch ($sort) {
      case 'price_asc':
        $args['meta_key'] = 'price';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'ASC';
        break;
      case 'price_desc':
        $args['meta_key'] = 'price';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'DESC';
        break;
      case 'date_asc':
        $args['orderby'] = 'date';
        $args['order']   = 'ASC';
        break;
      case 'date_desc':
      default:
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
        break;
    }
  }

  return array_merge($args, $override);
}

if ( ! function_exists('sora_render_feed_grid_from_query') ) {
  function sora_render_feed_grid_from_query( $q ) {
    ob_start();
    if ( $q && $q->have_posts() ) {
      echo '<div class="feed-grid">';
      while ( $q->have_posts() ) {
        $q->the_post();
        sora_render_feed_card();
      }
      echo '</div>';
    } else {
      echo '<p>該当する作品はありません。</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
  }
}

// =============================
// Ajax: 投稿フィルター実行
// =============================
function ajax_filter() {
  ob_clean(); // clear buffer to avoid stray output

  $cache_key = 'ajax_filter_' . md5(serialize($_POST));
  $cached    = get_transient($cache_key);
  if ($cached !== false) {
    wp_send_json($cached);
  }

  $params = sanitize_array($_POST);

  $args  = build_filter_query_args($params, ['posts_per_page' => -1]);
  $query = new WP_Query($args);

  // Build the HTML using the same card UI as the template
  $html = sora_render_feed_grid_from_query($query);

  // =============================
  // AjaxレスポンスとしてHTMLを返却
  // =============================
  $response = ['html' => $html];
  set_transient($cache_key, $response, MINUTE_IN_SECONDS * 2);
  wp_send_json($response);
}

// =============================
// Ajax アクションの登録（ログイン済・未ログイン両対応）
// =============================
add_action('wp_ajax_ajax_filter', 'ajax_filter');
add_action('wp_ajax_nopriv_ajax_filter', 'ajax_filter');

// --------------------------------------
//  Ajax: フィルター件数取得（各項目のグレーアウト制御用）
// --------------------------------------
add_action('wp_ajax_filter_term_counts', 'handle_filter_term_counts');
add_action('wp_ajax_nopriv_filter_term_counts', 'handle_filter_term_counts');

/**
 * 指定された key を持つ meta_query 条件を再帰的に削除
 * （現状未使用だが、メタクエリから条件除外したい場合に使える）
 */
function remove_meta_query_keys_recursive(array $query, array $keys) {
  foreach ($query as $index => $clause) {
    if ($index === 'relation') continue;

    if (is_array($clause)) {
      if (isset($clause['key']) && in_array($clause['key'], $keys, true)) {
        unset($query[$index]);
        continue;
      }

      $query[$index] = remove_meta_query_keys_recursive($clause, $keys);
      if ($query[$index] === []) unset($query[$index]);
    }
  }
  return $query;
}

/**
 * 各フィルター項目ごとの「該当件数」を算出して返すAjax処理
 * 結果はJS側でグレーアウト処理に使用
 */
function handle_filter_term_counts() {
  $cache_key = 'filter_term_counts_' . md5(serialize($_POST));
  $cached = get_transient($cache_key);
  if ($cached !== false) {
    wp_send_json($cached);
  }

  $post = sanitize_array($_POST);

  $result = [];

  // 対象のタクソノミー（カテゴリー、タグ、voice_pitch、level）
  $target_taxonomies = ['category', 'post_tag', 'voice_pitch', 'level'];

  // 対象のスライダー項目（数値絞り込み）
  $sliders = [
    'price', 'ejaculation_count', 'instruction',
    'guide', 'countdown_shot', 'edging_count'
  ];

  // -------------------------------
  // 現在の選択に基づく tax_query/meta_query のベース構築
  // -------------------------------

  $tax_query_base = [];
  $meta_query_base = [];

  foreach ($target_taxonomies as $tax) {
    if (!empty($post[$tax])) {
      $tax_query_base[] = [
        'taxonomy' => $tax,
        'field'    => 'slug',
        'terms'    => (array) $post[$tax],
        'operator' => 'IN',
      ];
    }
  }

  foreach ($sliders as $slider_key) {
    $min_key = $slider_key . '_min';
    $max_key = $slider_key . '_max';
    if (isset($post[$min_key]) && isset($post[$max_key])) {
      $meta_query_base[] = [
        'key'     => $slider_key,
        'value'   => [intval($post[$min_key]), intval($post[$max_key])],
        'type'    => 'NUMERIC',
        'compare' => 'BETWEEN',
      ];
    }
  }

  // -------------------------------
  // 各タクソノミーにおける term ごとの該当件数を取得
  // -------------------------------

  foreach ($target_taxonomies as $taxonomy) {
    $terms = get_terms([
      'taxonomy'   => $taxonomy,
      'hide_empty' => false,
    ]);

    $term_data = [];

    foreach ($terms as $term) {
      $tax_query = $tax_query_base;
      $tax_query[] = [
        'taxonomy' => $taxonomy,
        'field'    => 'slug',
        'terms'    => $term->slug,
        'operator' => 'IN',
      ];

      $meta_query = $meta_query_base;

      $query = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'tax_query'      => count($tax_query) > 1 ? ['relation' => 'AND'] + $tax_query : $tax_query,
        'meta_query'     => count($meta_query) > 1 ? ['relation' => 'AND'] + $meta_query : $meta_query,
      ]);

      $term_data[] = [
        'slug'  => $term->slug,
        'count' => $query->found_posts,
      ];
    }

    $result[$taxonomy] = $term_data;
  }

  // -------------------------------
  // voice_pitch（カスタム）の件数取得（再定義）
  // -------------------------------
  $voice_pitch_terms = get_terms(['taxonomy' => 'voice_pitch', 'hide_empty' => false]);
  $result['voice_pitch'] = [];

  foreach ($voice_pitch_terms as $term) {
    $tax_query = $tax_query_base;
    $tax_query[] = [
      'taxonomy' => 'voice_pitch',
      'field'    => 'slug',
      'terms'    => [$term->slug],
      'operator' => 'IN',
    ];

    $args = [
      'post_type'      => 'post',
      'post_status'    => 'publish',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'tax_query'      => count($tax_query) > 1 ? array_merge(['relation' => 'AND'], $tax_query) : $tax_query,
      'meta_query'     => count($meta_query_base) > 1 ? array_merge(['relation' => 'AND'], $meta_query_base) : $meta_query_base,
    ];

    $query = new WP_Query($args);
    $result['voice_pitch'][] = [
      'slug'  => $term->slug,
      'count' => $query->found_posts,
    ];
  }

  // -------------------------------
  // level（カスタム）の件数取得
  // -------------------------------
  $level_terms = get_terms(['taxonomy' => 'level', 'hide_empty' => false]);
  $result['level'] = [];

  foreach ($level_terms as $term) {
    $tax_query = $tax_query_base;
    $tax_query[] = [
      'taxonomy' => 'level',
      'field'    => 'slug',
      'terms'    => [$term->slug],
      'operator' => 'IN',
    ];

    $args = [
      'post_type'      => 'post',
      'post_status'    => 'publish',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'tax_query'      => count($tax_query) > 1 ? array_merge(['relation' => 'AND'], $tax_query) : $tax_query,
      'meta_query'     => count($meta_query_base) > 1 ? array_merge(['relation' => 'AND'], $meta_query_base) : $meta_query_base,
    ];

    $query = new WP_Query($args);
    $result['level'][] = [
      'slug'  => $term->slug,
      'count' => $query->found_posts,
    ];
  }
	
// --------------------------------------
// 🎯 touchedされていないスライダーの min/max を取得
// --------------------------------------
// ユーザーが操作していないスライダーについて、
// 現在のフィルター条件下で利用可能な範囲（min/max）を取得する
$slider_keys = ['price', 'ejaculation_count', 'instruction', 'guide', 'countdown_shot', 'edging_count'];
$slider_results = [];

foreach ($slider_keys as $key) {
  $touched = isset($post["touched_{$key}"]) && $post["touched_{$key}"] === '1';

  if ($touched) {
    continue; // 既に操作済みならスキップ（そのスライダーの範囲は確定済みのため）
  }

  // WP_Query 条件構築
  $args = [
    'post_type'      => 'post',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'fields'         => 'ids',
    'tax_query'      => count($tax_query_base) > 1 ? ['relation' => 'AND'] + $tax_query_base : $tax_query_base,
    'meta_query'     => count($meta_query_base) > 1 ? ['relation' => 'AND'] + $meta_query_base : $meta_query_base,
  ];

  // このスライダーのメタ値が存在する投稿に限定
  $args['meta_query'][] = [
    'key'     => $key,
    'compare' => 'EXISTS',
    'type'    => 'NUMERIC',
  ];

  $query = new WP_Query($args);
  $values = [];

  foreach ($query->posts as $post_id) {
    $val = get_post_meta($post_id, $key, true);
    if (is_numeric($val)) {
      $values[] = floatval($val);
    }
  }

  // min/max 値を格納
  if (!empty($values)) {
    $slider_results[$key] = [
      'min' => min($values),
      'max' => max($values),
    ];
  }
}

$result['sliders'] = $slider_results;

// --------------------------------------
// 🎯 各スライダー項目の min/max/count を返す処理（touched関係なし）
// --------------------------------------
// JS側の件数表示や再構築に利用
$slider_result = [];

foreach ($sliders as $slider_key) {
  // このスライダー以外の条件でフィルター構築
  $meta_query = remove_meta_query_keys_recursive($meta_query_base, [$slider_key]);
  $meta_query = array_values($meta_query);

  $query = new WP_Query([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'tax_query'      => count($tax_query_base) > 1 ? array_merge(['relation' => 'AND'], $tax_query_base) : $tax_query_base,
    'meta_query'     => count($meta_query) > 1 ? array_merge(['relation' => 'AND'], $meta_query) : $meta_query,
  ]);

  $values = [];

  foreach ($query->posts as $post_id) {
    $val = get_post_meta($post_id, $slider_key, true);
    if (is_numeric($val)) {
      $values[] = floatval($val);
    }
  }

  // 値がある場合のみ返す（countは該当投稿数）
  if (!empty($values)) {
    $slider_result[$slider_key] = [
      'min'   => min($values),
      'max'   => max($values),
      'count' => count($values),
    ];
  } else {
    // 値がない場合はnullで初期化
    $slider_result[$slider_key] = [
      'min'   => null,
      'max'   => null,
      'count' => 0,
    ];
  }
}

// touched=false のスライダー結果に全件min/max/countを上書き
$result['sliders'] = array_merge($slider_results, $slider_result);

// JSON形式で結果を返却
set_transient($cache_key, $result, MINUTE_IN_SECONDS * 2);
wp_send_json($result);
}

// --------------------------------------
// 🎞 カスタム投稿タイプ「ショート動画」定義
// --------------------------------------
function register_short_videos_post_type() {
  register_post_type('short_videos', [
    'labels' => [
      'name'               => 'ショート動画',
      'singular_name'      => 'ショート動画',
      'add_new'            => '新規追加',
      'add_new_item'       => '新しいショート動画を追加',
      'edit_item'          => 'ショート動画を編集',
      'new_item'           => '新しいショート動画',
      'view_item'          => 'ショート動画を見る',
      'search_items'       => 'ショート動画を検索',
      'not_found'          => '見つかりませんでした',
      'not_found_in_trash' => 'ゴミ箱内に見つかりませんでした',
    ],
    'public'        => true,
    'has_archive'   => true,
    'menu_position' => 5,
    'menu_icon'     => 'dashicons-video-alt3',
    'supports'      => ['title', 'editor', 'thumbnail', 'author'],
    'show_in_rest'  => true, // ブロックエディタ対応
    'rewrite'       => ['slug' => 'shorts'], // URLスラッグ
  ]);
}
add_action('init', 'register_short_videos_post_type');



// 🔧 指定したタクソノミーの各用語ごとの投稿数を取得
function get_term_post_counts_by_taxonomy($taxonomy) {
  static $cache = [];
  if (isset($cache[$taxonomy])) {
    return $cache[$taxonomy];
  }

  $transient_key = 'pf_term_counts_' . $taxonomy;
  $counts = get_transient($transient_key);
  if ($counts !== false) {
    $cache[$taxonomy] = $counts;
    return $counts;
  }

  $terms = get_terms([
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
  ]);

  $counts = wp_list_pluck($terms, 'count', 'slug');

  set_transient($transient_key, $counts, HOUR_IN_SECONDS);
  $cache[$taxonomy] = $counts;
  return $counts;
}

// --------------------------------------------------
// 📌 Insert the post title after the first block in post content
// --------------------------------------------------
add_filter('the_content', 'sora_insert_title_after_first_block', 5);
function sora_insert_title_after_first_block($content) {
  // Only for single posts in the main query within the loop
  if (!is_single() || !in_the_loop() || !is_main_query()) {
    return $content;
  }

  // Avoid running twice
  if (strpos($content, 'sora-moved-title') !== false) {
    return $content;
  }

  $title = get_the_title();

  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  $dom->loadHTML('<?xml encoding="utf-8"?><div id="sora-root">' . $content . '</div>');
  libxml_clear_errors();

  $root = $dom->getElementById('sora-root');
  if (!$root) {
    return $content;
  }

  $firstElement = null;
  foreach ($root->childNodes as $child) {
    if ($child->nodeType === XML_ELEMENT_NODE) {
      $firstElement = $child;
      break;
    }
  }

  $h1 = $dom->createElement('h1', $title);
  $h1->setAttribute('class', 'sora-moved-title');

  if ($firstElement) {
    if ($firstElement->nextSibling) {
      $root->insertBefore($h1, $firstElement->nextSibling);
    } else {
      $root->appendChild($h1);
    }
  } else {
    $root->appendChild($h1);
  }

  $newContent = '';
  foreach ($root->childNodes as $child) {
    $newContent .= $dom->saveHTML($child);
  }

  return $newContent;
}

// Return a comma-separated list of linked term names for the first taxonomy that has terms.
// $tax_slugs can be a string or an array of taxonomy slugs.
if ( ! function_exists('sora_get_term_links_html') ) {
  function sora_get_term_links_html( $post_id, $tax_slugs ) {
    $tax_slugs = (array) $tax_slugs;
    foreach ( $tax_slugs as $tax ) {
      $terms = get_the_terms( $post_id, $tax );
      if ( $terms && ! is_wp_error($terms) ) {
        $links = array();
        foreach ( $terms as $t ) {
          $url = get_term_link( $t );
          if ( ! is_wp_error($url) ) {
            $links[] = '<a href="' . esc_url($url) . '" class="meta-link" rel="tag">' . esc_html($t->name) . '</a>';
          }
        }
        if ( $links ) return implode(', ', $links);
      }
    }
    return ''; // no terms found on any of the provided taxonomies
  }
}

// Normalize an ACF Taxonomy field value to an array of WP_Term objects.
// $field_value may be: int ID, WP_Term, array of IDs/Terms, or null.
if ( ! function_exists('sora_normalize_terms_from_acf') ) {
  function sora_normalize_terms_from_acf( $field_value, $fallback_tax = '' ) {
    if ( ! $field_value ) return [];

    $vals  = is_array($field_value) ? $field_value : [ $field_value ];
    $terms = [];

    foreach ( $vals as $v ) {
      if ( $v instanceof WP_Term ) {
        $terms[] = $v;
      } elseif ( is_numeric($v) ) {
        $t = get_term( (int) $v );
        if ( $t && ! is_wp_error($t) ) $terms[] = $t;
      } elseif ( is_array($v) && isset($v['term_id']) ) {
        // Some plugins return associative arrays shaped like terms
        $t = get_term( (int) $v['term_id'] );
        if ( $t && ! is_wp_error($t) ) $terms[] = $t;
      } elseif ( is_string($v) && $fallback_tax ) {
        // As a last resort try to resolve by name
        $maybe = get_terms([
          'taxonomy'   => $fallback_tax,
          'name'       => $v,
          'hide_empty' => false,
          'number'     => 1,
        ]);
        if ( ! is_wp_error($maybe) && $maybe ) $terms[] = $maybe[0];
      }
    }
    return $terms;
  }
}

// Format a list of terms into linked HTML.
if ( ! function_exists('sora_terms_links_html') ) {
  function sora_terms_links_html( $terms ) {
    if ( ! $terms ) return '';
    $links = [];
    foreach ( $terms as $t ) {
      $url = get_term_link( $t );
      if ( ! is_wp_error($url) ) {
        $links[] = '<a class="meta-link" href="'.esc_url($url).'" rel="tag">'.esc_html($t->name).'</a>';
      }
    }
    return implode(', ', $links);
  }
}

// === DLsite-style feed card with more info ===
if ( ! function_exists('sora_render_feed_card') ) {
  function sora_render_feed_card( $post_id = null ) {
    $post_id = $post_id ?: get_the_ID();

    // Retrieve meta fields
    $price  = get_post_meta($post_id, 'price', true);
    ?>
    <article class="feed-card" data-post-id="<?php echo esc_attr($post_id); ?>">
      <a class="feed-thumb" href="<?php echo esc_url( get_permalink($post_id) ); ?>">
        <?php
        if ( has_post_thumbnail($post_id) ) {
          echo get_the_post_thumbnail($post_id, 'medium_large', ['loading'=>'lazy']);
        } else {
          echo '<div class="feed-thumb--placeholder"></div>';
        }
        ?>
      </a>

      <div class="feed-body">
        <!-- Title (2 lines max) -->
        <h3 class="feed-title">
          <a href="<?php echo esc_url( get_permalink($post_id) ); ?>">
            <?php echo esc_html( wp_trim_words( get_the_title($post_id), 40 ) ); ?>
          </a>
        </h3>

        <!-- Circle / Voice Actor (taxonomy linked; supports ACF Taxonomy fields) -->
        <div class="feed-meta">
          <?php
          // 1) Try to read from ACF Taxonomy fields first (field names, not slugs)
          $acf_circle   = get_field('circle_name',  $post_id);   // ACF Taxonomy field
          $acf_actors   = get_field('voice_actors', $post_id);   // ACF Taxonomy field

          // If you know the actual taxonomy slugs, set them here:
          $circle_tax   = 'circle_name';   // e.g. 'circle' or 'circle_name'
          $actors_tax   = 'voice_actors';  // e.g. 'voice_actor' or 'voice_actors'

          $circle_terms = sora_normalize_terms_from_acf($acf_circle, $circle_tax);
          $actor_terms  = sora_normalize_terms_from_acf($acf_actors, $actors_tax);

          // 2) If ACF didn’t yield, fall back to WP assigned terms by slug
          if ( ! $circle_terms ) {
            $try = get_the_terms($post_id, $circle_tax);
            if ( $try && ! is_wp_error($try) ) $circle_terms = $try;
          }
          if ( ! $actor_terms ) {
            $try = get_the_terms($post_id, $actors_tax);
            if ( $try && ! is_wp_error($try) ) $actor_terms = $try;
          }

          // 3) Render
          $circle_html = sora_terms_links_html($circle_terms);
          $actor_html  = sora_terms_links_html($actor_terms);
          ?>

          <?php if ( $circle_html ) : ?>
            <div class="meta-line circle"><?php echo $circle_html; ?></div>
          <?php endif; ?>

          <?php if ( $actor_html ) : ?>
            <div class="meta-line cv">CV: <?php echo $actor_html; ?></div>
          <?php endif; ?>
        </div>

        <!-- Price -->
        <div class="feed-price-row">
          <?php if ($price !== ''): ?>
            <span class="price-strong"><?php echo number_format_i18n((int)$price); ?><span class="yen">円</span></span>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <?php
          // Retrieve ACF URL field (string value)
          $dlurl   = get_field('dlsite_url', $post_id);

          // Use DLsite if available, otherwise post permalink
          $target  = $dlurl ?: get_permalink($post_id);
          $label   = $dlurl ? 'DLsiteへ' : '詳細';
          $classes = $dlurl ? 'btn btn-dlsite' : 'btn btn-outline';
          $attr    = $dlurl ? ' target="_blank" rel="noopener nofollow sponsored"' : '';
        ?>
        <div class="feed-actions">
          <a class="<?php echo esc_attr($classes); ?>"
             href="<?php echo esc_url($target); ?>"<?php echo $attr; ?>>
            <?php echo esc_html($label); ?>
          </a>
        </div>
      </div>

      <!-- Stretched link: make the whole card clickable -->
      <a class="card-stretched-link"
         href="<?php echo esc_url( get_permalink($post_id) ); ?>"
         aria-label="記事詳細へ"></a>
    </article>
    <?php
  }
}

// Disable SWELL's top featured image on single posts
add_filter('swell_is_show_thumb', function ($show) {
  if (is_single()) return false;
  return $show;
}, 10);