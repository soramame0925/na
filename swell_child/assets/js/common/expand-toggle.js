(function(){
  const MOBILE_BREAKPOINT = 768; // モバイル幅の基準

  // PC幅か判定
  const isDesktop = () => window.matchMedia(`(min-width: ${MOBILE_BREAKPOINT}px)`).matches;

  // パネルを body 直下へ移動（position:fixed のズレ防止）
  const movePanelsToBody = () => {
    document.querySelectorAll('.sora-expand-panel').forEach(p => document.body.appendChild(p));
  };

  function initToggle(){
    if (isDesktop()) return; // PCは処理しない

    movePanelsToBody();

    const buttons = document.querySelectorAll('.expand-icon[data-target]');
    const scrollContainer = document.querySelector('.sora-random-wrapper') || window;
    let openScrollY = 0;

    const closePanels = () => {
      document.querySelectorAll('.sora-expand-panel').forEach(p => p.classList.remove('active'));
      document.body.classList.remove('expand-active');
      scrollContainer.removeEventListener('scroll', onScroll, true);
    };
    // 外部からも呼べるように公開
    window.closeExpandPanels = closePanels;

    const onScroll = () => {
      const pos = scrollContainer === window ? window.scrollY : scrollContainer.scrollTop;
      if (Math.abs(pos - openScrollY) > 30) closePanels();
    };

    buttons.forEach(btn => {
      btn.addEventListener('click', e => {
        const id = btn.dataset.target;
        document.querySelectorAll('.sora-expand-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('expand-' + id);
        if (panel) {
          panel.classList.add('active');
          document.body.classList.add('expand-active');
          openScrollY = scrollContainer === window ? window.scrollY : scrollContainer.scrollTop;
          scrollContainer.addEventListener('scroll', onScroll, true);
        }
        e.stopPropagation();
      });
    });

    // パネル以外をクリックしたら閉じる
    document.addEventListener('click', closePanels);
  }

  document.addEventListener('DOMContentLoaded', initToggle);
})();
