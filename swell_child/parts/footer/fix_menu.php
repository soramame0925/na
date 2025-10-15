<?php
// Guard against duplicate render in templates
if (defined('SORA_FIX_MENU_RENDERED')) { return; }
define('SORA_FIX_MENU_RENDERED', true);
?>
<nav id="fix_bottom_menu"
     class="fixed-bottom-nav"
     role="navigation"
     aria-label="Bottom Navigation">
  <ul class="fixed-bottom-nav__list" role="list">
    <li class="fixed-bottom-nav__item">
      <a class="fixed-bottom-nav__btn" href="/" aria-label="Home">🏠<span>ホーム</span></a>
    </li>
    <li class="fixed-bottom-nav__item">
      <button type="button" class="fixed-bottom-nav__btn" data-onclick="toggleFilter" aria-label="Filter">🔎<span>フィルター</span></button>
    </li>
    <li class="fixed-bottom-nav__item">
      <a class="fixed-bottom-nav__btn" href="/discover" aria-label="Shorts">▶️<span>ショート</span></a>
    </li>
    <li class="fixed-bottom-nav__item">
      <a class="fixed-bottom-nav__btn" href="/search" aria-label="Search">🔍<span>検索</span></a>
    </li>
    <li class="fixed-bottom-nav__item">
      <a class="fixed-bottom-nav__btn" href="/library" aria-label="Library">📚<span>ライブラリ</span></a>
    </li>
  </ul>
</nav>
