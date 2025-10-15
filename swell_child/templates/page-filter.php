<?php
/*
Template Name: Filter Page
Template Post Type: page
*/

/**
 * フィルター検索ページ用テンプレート
 * - 絞り込みUIのHTMLを生成し、JSによるAjaxフィルタリングと連携
 */

get_header();

// -----------------------------------------------------------------------------
//  Utility functions
// -----------------------------------------------------------------------------

/**
 * Sanitize and cache request parameters (GET or POST).
 */
function pf_request_params() {
    static $params = null;
    if ($params !== null) {
        return $params;
    }
    $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $params = function_exists('sanitize_array') ? sanitize_array($source) : $source;
    return $params;
}

/**
 * Fetch terms with simple static caching.
 */
function pf_get_terms_cached($taxonomy, array $args = []) {
    static $cache = [];
    $key = $taxonomy . md5(json_encode($args));
    if (!isset($cache[$key])) {
        $cache[$key] = get_terms(array_merge(['taxonomy' => $taxonomy, 'hide_empty' => false], $args));
    }
    return $cache[$key];
}

/**
 * Fetch child categories of a parent slug with caching.
 */
function pf_get_child_categories($parent_slug) {
    static $cache = [];
    if (isset($cache[$parent_slug])) {
        return $cache[$parent_slug];
    }
    $parent = get_category_by_slug($parent_slug);
    $cache[$parent_slug] = $parent ? get_categories(['hide_empty' => true, 'parent' => $parent->term_id]) : [];
    return $cache[$parent_slug];
}

/**
 * Get single term by slug with caching.
 */
function pf_get_term_by_slug_cached($slug, $taxonomy) {
    static $cache = [];
    $key = $taxonomy . ':' . $slug;
    if (!isset($cache[$key])) {
        $cache[$key] = get_term_by('slug', $slug, $taxonomy);
    }
    return $cache[$key];
}

/**
 * Render checkbox list for provided terms.
 */
function pf_render_term_checkboxes($terms, $taxonomy, $label) {
    echo '<div class="filter-group"><h3>' . esc_html($label) . '</h3>';

    if (empty($terms)) {
        echo '<p>項目が見つかりませんでした。</p></div>';
        return;
    }

    // ✅ 初期件数を取得（可能なら渡された配列から取得）
    $term_counts = wp_list_pluck($terms, 'count', 'slug');

    // count プロパティが存在しない場合のフォールバック
    if (empty($term_counts)) {
        $term_counts = get_term_post_counts_by_taxonomy($taxonomy);
    }
    $selected = (array) (pf_request_params()[$taxonomy] ?? []);

    foreach ($terms as $term) {
        $count = isset($term_counts[$term->slug]) ? $term_counts[$term->slug] : 0;
        $checked = in_array($term->slug, $selected, true) ? 'checked' : '';
        $hide    = ($count === 0 && !$checked) ? 'filter-hidden' : '';

        echo '<label class="' . esc_attr($hide) . '">';
        echo '<input type="checkbox" name="' . esc_attr($taxonomy) . '[]" value="' . esc_attr($term->slug) . '" ' . $checked . '> ';
        echo esc_html($term->name) . ' <span class="term-count">(' . $count . ')</span>';
        echo '</label>';
    }

    echo '</div>';
}

// -----------------------------------------------------------------------------
//  フィルターUI表示部品の定義
// -----------------------------------------------------------------------------


/**
 * ✅ 現在の絞り込み条件（アクティブチップ）を表示
 * - 各タクソノミーごとにチェックされたスラッグを取得
 * - 名前＋削除ボタンのチップ形式で出力
 */
function pf_active_filter_chips() {
    $taxonomies = [
        'category'    => 'カテゴリ',
        'post_tag'    => 'タグ',
        'voice_pitch' => '声のタイプ',
        'level'       => 'レベル',
    ];
    $params = pf_request_params();
    ?>
   <div class="active-filters hidden-on-load">
    <?php foreach ($taxonomies as $tax => $label) : ?>
        <?php if (!empty($params[$tax])) : ?>
            <?php foreach ((array) $params[$tax] as $slug) :
                $term = pf_get_term_by_slug_cached($slug, $tax);
                if ($term) : ?>
                <span class="filter-chip" data-type="<?php echo esc_attr($tax); ?>" data-value="<?php echo esc_attr($slug); ?>">
                    <?php echo esc_html($label); ?>: <?php echo esc_html($term->name); ?>
                    <button class="remove-chip" aria-label="<?php echo esc_attr($label); ?>削除">✕</button>
                </span>
            <?php endif; endforeach; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>
    <?php if (!empty($params)) : ?>
        <button type="button" class="reset-all-chips hidden-on-load" id="reset-all-chips">🗑️ 全てクリア</button>
    <?php endif;
}


/**
 * ✅ 親カテゴリ（例：声優・イラスト）に紐づく子カテゴリを出力
 * - チェックボックス形式で表示
 * - $slug: 親カテゴリのスラッグ
 * - $label: 表示用のラベル名
 */
function pf_category_filter($slug, $label) {
    $terms = pf_get_child_categories($slug);
    if ($terms === []) {
        echo '<div class="filter-group"><h3>' . esc_html($label) . '</h3><p>子カテゴリが見つかりませんでした。</p></div>';
        return;
    }
    pf_render_term_checkboxes($terms, 'category', $label);
}


/**
 * ✅ 投稿タグ（post_tag）をチェックボックス形式で表示
 * - フィルターUIでタグによる絞り込みを可能にする
 */
function pf_tag_filter() {
    $tags = pf_get_terms_cached('post_tag', ['hide_empty' => true]);
    pf_render_term_checkboxes($tags, 'post_tag', 'タグ');
}


/**
 * ✅ 任意のカスタムタクソノミーをフィルターUIとして出力
 * - level や voice_pitch 用
 * - レベルのみ並び順を指定（soft→medium→hard）
 */
function pf_taxonomy_filter($taxonomy, $label) {
    $terms = pf_get_terms_cached($taxonomy);
    if (empty($terms) || is_wp_error($terms)) {
        echo '<div class="filter-group"><h3>' . esc_html($label) . '</h3><p>項目が見つかりませんでした。</p></div>';
        return;
    }

    if ($taxonomy === 'level') {
        $desired_order = ['soft', 'medium', 'hard'];
        usort($terms, function ($a, $b) use ($desired_order) {
            return array_search($a->slug, $desired_order) - array_search($b->slug, $desired_order);
        });
    }

    pf_render_term_checkboxes($terms, $taxonomy, $label);
}

/**
 * ✅ スライダーUIを出力する関数
 *
 * @param string $label 表示用のラベル（例: 「値段」「射精回数」）
 * @param string $field 対象のカスタムフィールド名（例: 'price', 'ejaculation_count'）
 * @param string $unit 単位（例: '円', '回'）デフォルトは「回」
 * @param int    $min 最小値（スライダー初期値）
 * @param int    $max 最大値（スライダー初期値）
 * @param int    $step スライダーの刻み幅（デフォルト: 1）
 */
function pf_render_slider($label, $field, $unit = '回', $min = 0, $max = 10, $step = 1) {
    $params = pf_request_params();
    $minVal = isset($params[$field . '_min']) ? (int) $params[$field . '_min'] : $min;
    $maxVal = isset($params[$field . '_max']) ? (int) $params[$field . '_max'] : $max;

    echo '<div class="filter-group">';
    echo '<h3>' . esc_html($label) . '</h3>';
    echo '<div id="' . esc_attr($field) . '-slider"></div>'; // スライダー用の空div（JSで生成）
    echo '<div><span id="' . esc_attr($field) . '-min">' . esc_html($minVal) . '</span>' . esc_html($unit) . ' 〜 <span id="' . esc_attr($field) . '-max">' . esc_html($maxVal) . '</span>' . esc_html($unit) . '</div>';

    // Hidden入力で min/max を送信
    echo '<input type="hidden" name="' . esc_attr($field) . '_min" id="' . esc_attr($field) . '_min_input" value="' . esc_attr($minVal) . '">';
    echo '<input type="hidden" name="' . esc_attr($field) . '_max" id="' . esc_attr($field) . '_max_input" value="' . esc_attr($maxVal) . '">';
    echo '</div>';
}


/**
 * ✅ フィルター全体のフォームを描画
 * - カテゴリー・タグ・カスタムタクソノミー・スライダー・並び替えを含む
 */
function pf_render_filter_form() {
    ?>
    <form id="custom-filter-form" action="<?php echo esc_url(get_permalink()); ?>" method="GET">
        <div class="filter-inner">

            <?php pf_active_filter_chips(); ?>

            <!-- ✅ 親カテゴリごとのチェックボックス -->
            <?php
            $categories = [
                'voice-actor'  => '声優',
                'circle'       => 'サークル',
                'illustration' => 'イラスト',
            ];
            foreach ($categories as $slug => $label) {
                pf_category_filter($slug, $label);
            }
            ?>

            <!-- ✅ タグとカスタムタクソノミー -->
            <?php pf_tag_filter(); ?>
            <?php
            $taxonomies = [
                'voice_pitch' => '声のタイプ',
                'level'       => 'レベル',
            ];
            foreach ($taxonomies as $tax => $label) {
                pf_taxonomy_filter($tax, $label);
            }
            ?>

            <!-- ✅ スライダー系の数値入力項目 -->
            <button type="button" id="slider-toggle" class="slider-toggle">&#x25BC; スライダーフィルターを表示</button>
            <div id="slider-filters" class="slider-filters" style="display: none;">
            <?php
            $sliders = [
                ['label' => '値段（円）', 'field' => 'price', 'unit' => '円', 'min' => 0, 'max' => 10000, 'step' => 100],
                ['label' => '射精回数', 'field' => 'ejaculation_count'],
                ['label' => '射精命令', 'field' => 'instruction'],
                ['label' => '射精ガイド', 'field' => 'guide'],
                ['label' => 'カウントダウン射精', 'field' => 'countdown_shot'],
                ['label' => '寸止め回数', 'field' => 'edging_count'],
            ];
            foreach ($sliders as $cfg) {
                pf_render_slider(
                    $cfg['label'],
                    $cfg['field'],
                    $cfg['unit'] ?? '回',
                    $cfg['min']  ?? 0,
                    $cfg['max']  ?? 10,
                    $cfg['step'] ?? 1
                );
            }
            ?>
            </div>


            <!-- ✅ 閉じるボタン -->
            <button type="button" id="close-filter" class="close-button" aria-label="閉じる"></button>
        </div><!-- /.filter-inner -->

        <div class="filter-actions">
            <button type="submit" id="apply-btn">絞り込み</button>
            <button type="button" id="reset-btn">リセット</button>
        </div>
    </form>

    <!-- ✅ JSによるリセット処理 -->
    <script>
    document.getElementById('reset-btn').addEventListener('click', function () {
        const url = window.location.href.split('?')[0];
        window.location.href = url;
    });
    </script>
    <?php
}


/**
 * ✅ 検索結果を表示する関数
 * WP_Query を用いて該当する投稿を取得し、ループで出力
 */
function pf_render_results() {
    $args  = build_filter_query_args(
        pf_request_params(),
        [
            'posts_per_page' => 10,
            'paged'          => get_query_var('paged') ?: 1,
        ]
    );
    $query = new WP_Query($args);
    echo sora_render_feed_grid_from_query($query);
}
?>

<?php $sort_value = pf_request_params()['sort'] ?? 'date_desc'; ?>
<div class="sort-options">
    <label for="sort-select">並び順：</label>
    <select id="sort-select">
        <option value="date_desc" <?php selected($sort_value, 'date_desc'); ?>>🆕 新着順</option>
        <!-- 人気順は後日実装 -->
        <option value="popular" <?php selected($sort_value, 'popular'); ?>>🔥 人気順（未実装）</option>
        <option value="price_asc" <?php selected($sort_value, 'price_asc'); ?>>💰 価格が安い順</option>
        <option value="price_desc" <?php selected($sort_value, 'price_desc'); ?>>💸 価格が高い順</option>
    </select>
</div>

<main id="main" class="filter-page">

    <!-- 📄 検索結果セクション -->
    <section>
        <div class="filtered-results">
            <?php pf_render_results(); ?>
        </div>
    </section>
</main>

<!-- 🔽 横スライドで出るフィルターUI（全画面オーバーレイ） -->
<div class="filter-layer">
  <div class="filter-overlay" aria-hidden="true"></div>
  <aside id="filter-sidebar" class="filter-drawer" role="dialog" aria-modal="true" aria-label="Filter">
    <?php pf_render_filter_form(); ?>
  </aside>
</div>
<?php
  // 他の出力が終わったあと
  get_footer();
?>
