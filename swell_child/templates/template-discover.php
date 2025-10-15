<?php
/*
Template Name: Discover (Shorts)
Template Post Type: page
*/
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>

  <!-- ✅ Discover専用：全体レイアウトのリセットCSS -->
  <style>
  html, body {
    margin: 0 !important;
    padding: 0 !important;
    height: 100% !important;
    width: 100% !important;
    max-width: 100vw !important;
    overflow-x: hidden !important;
    background: #000 !important;
    box-sizing: border-box;
  }

  body.is-discover-page * {
    box-sizing: border-box !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
  }

  /* ✅ SWELLの共通UIを非表示にする */
  body.is-discover-page .l-footer,
  body.is-discover-page .l-header,
  body.is-discover-page .l-main,
  body.is-discover-page .l-fixBottomNav,
  body.is-discover-page .p-shareBtn,
  body.is-discover-page .p-fixBtn,
  body.is-discover-page .wp-block-search {
    display: none !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* ✅ 投稿表示エリアの余白を排除 */
  body.is-discover-page .sora-random-posts {
    margin: 0 !important;
    padding: 0 !important;
  }

  /* ✅ PC表示時にも縦1列・中央揃えにする */
  @media screen and (min-width: 768px) {
    body.is-discover-page .sora-random-posts {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3rem;
      padding: 2rem 0;
    }

    body.is-discover-page .sora-post-page {
      width: 420px;
      margin: 0 auto;
      padding: 0;
    }

    body.is-discover-page .sora-post-card {
      border-radius: 16px;
      overflow: hidden;
      background: #111;
      box-shadow: 0 0 12px rgba(0, 0, 0, 0.5);
    }
  }

          /* PC時に中央固定＋上下余白を追加して詰まりすぎ回避 */
@media screen and (min-width: 768px) {
  body.is-discover-page .sora-random-posts {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3rem;
    padding: 4rem 0 6rem; /* ← 上下に余白追加（上: 4rem、下: 6rem） */
  }

  body.is-discover-page .sora-post-page {
    width: 420px;
    margin: 0 auto;
    padding: 1.5rem 0; /* ← 各投稿の上下に余白追加 */
  }

  body.is-discover-page .sora-post-card {
    border-radius: 16px;
    overflow: hidden;
    background: #111;
    box-shadow: 0 0 12px rgba(0, 0, 0, 0.5);
  }
}

</style>

</head>

<!-- ✅ Discover専用クラスは body_class フィルターで付与 -->
<body <?php body_class(); ?>>

  <!-- ============================ -->
  <!-- Discover投稿一覧の出力領域 -->
  <!-- ============================ -->
  <div class="sora-random-posts">
    <?php get_template_part('template-parts/sora-random-posts'); ?>
  </div>

  <?php wp_footer(); ?>

  <!-- ✅ JSでも念のため非表示処理（ロード後） -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      [
        '.l-fixBottomNav',
        '.p-shareBtn',
        '.p-fixBtn',
        '.l-footer',
        '.l-header',
        '.l-main',
        '.wp-block-search'
      ].forEach(sel => {
        const el = document.querySelector(sel);
        if (el) el.style.display = 'none';
      });
    });
  </script>
</body>
</html>
