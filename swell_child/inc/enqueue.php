<?php
if ( ! defined('ABSPATH') ) exit;

add_action('wp_enqueue_scripts', function () {
  $dir = get_stylesheet_directory();
  $uri = get_stylesheet_directory_uri();

  // ===== Global assets =====
  $path = '/style.css';
  $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
  wp_enqueue_style('swell-child-style', $uri . $path, [], $ver);

  // Refresh bottom nav assets
  wp_dequeue_style('old-fix-menu');
  wp_dequeue_script('old-fix-menu');

  $path = '/assets/css/components/fix-menu.css';
  if (file_exists($dir . $path)) {
    wp_enqueue_style(
      'sora-fix-menu',
      $uri . $path,
      [],
      filemtime($dir . $path)
    );
  }

  $path = '/assets/js/common/fix-menu.js';
  if (file_exists($dir . $path)) {
    wp_enqueue_script(
      'sora-fix-menu',
      $uri . $path,
      [],
      filemtime($dir . $path),
      true
    );
  }

  $path = '/assets/js/common/expand-toggle.js';
  $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
  wp_enqueue_script('expand-toggle', $uri . $path, ['jquery'], $ver, true);
  wp_script_add_data('expand-toggle', 'strategy', 'defer');

  $page_id  = is_page() ? get_queried_object_id() : 0;
  $tpl_slug = get_page_template_slug();
  if (empty($tpl_slug) && $page_id) {
    $tpl_slug = get_page_template_slug($page_id);
  }

  $current_tpl      = $tpl_slug ?: '';
  $current_tpl_base = $current_tpl ? basename($current_tpl) : '';
  $is_template_match = ($current_tpl_base === 'page-filter.php');
  $is_search_like    = is_front_page() || $is_template_match;

  $is_discover_tpl  = ($current_tpl_base === 'template-discover.php');
  $is_discover_like = (is_page() && $is_discover_tpl) || is_singular('short_videos');

  // ===== Discover / Short =====
  if ($is_discover_like) {
    $css = $dir . '/assets/css/components/bottom-sheet.css';
    if (file_exists($css)) {
      wp_enqueue_style('sora-bottom-sheet', $uri . '/assets/css/components/bottom-sheet.css', [], filemtime($css));
    }

    $css = $dir . '/assets/css/pages/discover.css';
    if (file_exists($css)) {
      wp_enqueue_style('sora-discover', $uri . '/assets/css/pages/discover.css', [], filemtime($css));
    }

    $js = $dir . '/assets/js/common/bottom-sheet.js';
    if (file_exists($js)) {
      wp_enqueue_script('sora-bottom-sheet', $uri . '/assets/js/common/bottom-sheet.js', [], filemtime($js), true);
      wp_script_add_data('sora-bottom-sheet', 'strategy', 'defer');
      wp_localize_script('sora-bottom-sheet', 'SORA_BS', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('sora_bs_nonce'),
      ]);
    }

    $js = $dir . '/assets/js/pages/discover.js';
    if (file_exists($js)) {
      wp_enqueue_script('sora-discover', $uri . '/assets/js/pages/discover.js', [], filemtime($js), true);
      wp_script_add_data('sora-discover', 'strategy', 'defer');
    }
  }

  // ===== Search (Filter) page =====
  if ( $is_search_like ) {
    $path = '/assets/css/vendor/nouislider.min.css';
    $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
    wp_enqueue_style('nouislider', $uri . $path, [], $ver);

    $path = '/assets/js/vendor/nouislider.min.js';
    $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
    wp_enqueue_script('nouislider', $uri . $path, [], $ver, true);
    wp_script_add_data('nouislider', 'strategy', 'defer');

    $path = '/assets/css/pages/search.css';
    $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
    wp_enqueue_style('sora-search', $uri . $path, [], $ver);

    $path = '/assets/js/pages/search.js';
    $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
    wp_enqueue_script('sora-search', $uri . $path, ['nouislider'], $ver, true);
    wp_script_add_data('sora-search', 'strategy', 'defer');
    wp_localize_script('sora-search', 'SORA_SEARCH', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
  }

  // ===== Single post page =====
  if ( is_single() && get_post_type() === 'post' ) {
    wp_enqueue_style(
      'swiper',
      'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
      [],
      '11.0.0'
    );
    wp_enqueue_script(
      'swiper',
      'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
      [],
      '11.0.0',
      true
    );
    wp_script_add_data('swiper', 'strategy', 'defer');

    $path = '/assets/css/pages/post.css';
    $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
    wp_enqueue_style('sora-post', $uri . $path, [], $ver);

    $path = '/assets/js/pages/post.js';
    $ver  = file_exists($dir . $path) ? filemtime($dir . $path) : null;
    wp_enqueue_script('sora-post', $uri . $path, ['swiper'], $ver, true);
    wp_script_add_data('sora-post', 'strategy', 'defer');
  }
}, 99);

// Remove obsolete handles if enqueued elsewhere
add_action('wp_enqueue_scripts', function () {
  foreach (['swell-child-single', 'swell-child-single-gallery'] as $h) {
    wp_dequeue_style($h);  wp_deregister_style($h);
    wp_dequeue_script($h); wp_deregister_script($h);
  }
}, 100);

/*
// Debug enqueued assets (?assets=debug)
// add_action('wp_print_scripts', function () {
//   if (!isset($_GET['assets']) || $_GET['assets'] !== 'debug') return;
//   global $wp_scripts, $wp_styles;
//   error_log('[ASSETS] SCRIPTS=' . implode(',', array_keys($wp_scripts->queue)));
//   error_log('[ASSETS] STYLES='  . implode(',', array_keys($wp_styles->queue)));
// }, 999);
*/
