<?php get_header(); ?>

<main class="short-video-post">
  <article class="sora-post-card">

  <!-- 🎞 サムネイル（16:9）＋chobitプレイヤー（別ブロック） -->
  <div class="sora-card-media">
    <?php if ($thumb = get_field('thumbnail_image')) : ?>
      <img src="<?php echo esc_url($thumb['url']); ?>" alt="">
    <?php endif; ?>
  </div>

  <div class="short-audio-wrapper">
    <?php if ($embed = get_field('chobit_embed')) : ?>
      <div class="short-audio-player" data-embed='<?php echo esc_attr($embed); ?>'></div>
    <?php endif; ?>
  </div>

  <!-- 🧠 情報エリア（タイトル・CV・DLリンク） -->
  <div class="sora-card-content">
    <h2 class="sora-title"><?php the_title(); ?></h2>

    <p class="sora-subinfo">
      <?php if ($voice = get_field('voice_actors')) : ?>
        Voice: <?php echo esc_html($voice); ?> /
      <?php endif; ?>
      <?php if ($pitch = get_field('voice_pitch')) : ?>
        Pitch: <?php echo esc_html(is_array($pitch) ? implode(', ', $pitch) : $pitch); ?>
      <?php endif; ?>
    </p>

    <?php if ($dl_url = get_field('dlsite_url')) : ?>
      <a href="<?php echo esc_url($dl_url); ?>" target="_blank" rel="noopener" class="sora-dl-link">
        ▶ DLsiteで見る
      </a>
    <?php endif; ?>
  </div>

</article>

</main>

<?php get_footer(); ?>