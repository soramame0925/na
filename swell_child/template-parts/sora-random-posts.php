<?php
// ACF が存在しない環境でも致命的エラーにならないようラッパーを定義
if ( ! function_exists( 'sora_field' ) ) {
  function sora_field( $field, $post_id = null ) {
    return function_exists( 'get_field' ) ? get_field( $field, $post_id ) : null;
  }
}

$random_posts = new WP_Query([
  'post_type'      => 'short_videos',
  'posts_per_page' => 10,
  'orderby'        => 'rand',
]);
?>

<div class="sora-random-wrapper">
  <?php while ( $random_posts->have_posts() ) : $random_posts->the_post(); ?>
    <section class="sora-post-page">
      <article class="sora-post-card">

        <div class="sora-card-media">
          <!-- サムネイル画像 -->
          <?php if ( $thumb = sora_field( 'thumbnail_image' ) ) : ?>
            <img src="<?php echo esc_url( $thumb['url'] ); ?>" alt="">
          <?php endif; ?>
        </div>

        <!-- chobitプレイヤー（遅延読み込み） -->
        <?php if ( $embed = sora_field( 'chobit_embed' ) ) : ?>
          <div class="sora-chobit-wrapper">
            <div class="short-audio-player">
              <?php echo $embed; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="sora-card-content">
         <!-- タイトル -->
<div class="sora-card-title"><?php the_title(); ?></div>

<!-- レビュー記事リンク（ボトムシート起動） -->
<?php
  // 失敗時フォールバック：紐付けレビューがあればそちら、なければ自分のパーマリンク
  $linked = sora_field( 'linked_review' );

  // ACF の返り値が配列/オブジェクト/ID など様々な形に対応
  if ( is_array( $linked ) ) {
    if ( isset( $linked['ID'] ) ) {
      $linked = $linked['ID'];
    } elseif ( isset( $linked[0] ) ) {
      $first = $linked[0];
      $linked = is_array( $first ) && isset( $first['ID'] ) ? $first['ID'] : $first;
    }
  } elseif ( is_object( $linked ) ) {
    $linked = $linked->ID ?? 0;
  }

  $linked       = is_numeric( $linked ) ? absint( $linked ) : 0;
  $fallback_url = $linked ? get_permalink( $linked ) : get_permalink();
?>
<button class="review-button"
        data-post-id="<?php echo get_the_ID(); ?>"
        data-href="<?php echo esc_url( get_permalink( $linked ) ); ?>"
        type="button">レビューを見る</button>



<?php
  $voice        = sora_field( 'voice_actors' );
  $circle       = sora_field( 'circle_name' );
  $pitch_field  = sora_field( 'voice_pitch' );

  // voice_pitch は ACF の設定によって配列・文字列・オブジェクトなど
  // 多様な形で返る可能性があるため、すべて文字列の配列に整形する
  if ( is_array( $pitch_field ) ) {
    $pitch_items = [];
    foreach ( $pitch_field as $item ) {
      if ( is_object( $item ) && isset( $item->name ) ) {
        $pitch_items[] = $item->name;
      } elseif ( is_array( $item ) && isset( $item['name'] ) ) {
        $pitch_items[] = $item['name'];
      } elseif ( is_scalar( $item ) ) {
        $pitch_items[] = $item;
      }
    }
    $pitch = implode( ', ', $pitch_items );
  } else {
    $pitch = $pitch_field;
  }

  $count_label  = sora_field( 'custom_count_label' );
  $count_value  = sora_field( 'custom_count_value' );
?>

<div class="sora-expand-ui">
  <!-- 左ボタンたち -->
  <div class="sora-icon-group left">
    <div class="expand-item">
      <button class="expand-icon" data-target="info-<?php echo get_the_ID(); ?>">🎙️</button>
    </div>
    <div class="expand-item">
      <button class="expand-icon" data-target="info-<?php echo get_the_ID(); ?>">🏢</button>
    </div>
    <div class="expand-item">
      <button class="expand-icon" data-target="info-<?php echo get_the_ID(); ?>">📢</button>
    </div>
    <div class="expand-item">
      <button class="expand-icon" data-target="info-<?php echo get_the_ID(); ?>">🔢</button>
    </div>
  </div>

  <!-- 右ボタン：DLリンクなど -->
  <div class="sora-icon-group right">
    <?php if ( $dl_url = sora_field( 'dlsite_url' ) ) : ?>
      <a href="<?php echo esc_url( $dl_url ); ?>" target="_blank" rel="noopener" class="expand-icon">⬇️</a>
    <?php endif; ?>
  </div>

  <!-- 展開パネルはここ（外に出すのがベスト） -->
  <div class="sora-expand-panel" id="expand-info-<?php echo get_the_ID(); ?>">
    <div class="expand-info-grid">
      <?php if ( $voice ) : ?>
        <div class="info-item">声優：<?php echo esc_html( $voice ); ?></div>
      <?php endif; ?>
      <?php if ( $circle ) : ?>
        <div class="info-item">サークル：<?php echo esc_html( $circle ); ?></div>
      <?php endif; ?>
      <?php if ( $pitch ) : ?>
        <div class="info-item">声のタイプ：<?php echo esc_html( $pitch ); ?></div>
      <?php endif; ?>
      <?php if ( $count_label && $count_value ) : ?>
        <div class="info-item"><?php echo esc_html( $count_label ); ?>：<?php echo esc_html( $count_value ); ?>回</div>
      <?php endif; ?>
    </div>
  </div>
</div>




      </article>
    </section>
  <?php endwhile; wp_reset_postdata(); ?>
</div>