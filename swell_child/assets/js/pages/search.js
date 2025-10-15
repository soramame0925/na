(() => {
  if (window.__soraSearchBound) return; window.__soraSearchBound = true;
  'use strict';

  const AJAX_URL = (window.SORA_SEARCH && SORA_SEARCH.ajaxUrl) ? SORA_SEARCH.ajaxUrl : '';

  // =============================
  // 設定・定数
  // =============================
  // スライダー対象のカスタムフィールド
  const sliderKeys = ['price', 'ejaculation_count', 'instruction', 'guide', 'countdown_shot', 'edging_count'];
  // スライダーごとの初期設定
  const sliderConfig = {
    price: { min: 0, max: 10000, step: 100 },
    ejaculation_count: { min: 0, max: 10, step: 1 },
    instruction: { min: 0, max: 10, step: 1 },
    guide: { min: 0, max: 10, step: 1 },
    countdown_shot: { min: 0, max: 10, step: 1 },
    edging_count: { min: 0, max: 10, step: 1 }
  };
  // スライダー表示名（チップ用）
  const sliderLabels = {
    price: '値段',
    ejaculation_count: '射精回数',
    instruction: '射精命令',
    guide: '射精ガイド',
    countdown_shot: 'カウントダウン射精',
    edging_count: '寸止め回数'
  };
  const taxLabels = { category: 'カテゴリ', post_tag: 'タグ', voice_pitch: '声のタイプ', level: 'レベル' };

  // DOM セレクタ定義
  const SELECTORS = {
    form: '#custom-filter-form',
    results: '.filtered-results',
    active: '.active-filters',
    sidebar: '#filter-sidebar',
    layer: '.filter-layer',
    overlay: '.filter-layer .filter-overlay',
    closeBtn: '#close-filter',
    sliderToggle: '#slider-toggle',
    sliderContainer: '#slider-filters',
    header: 'header.l-header',
    bottomNav: '#fix_bottom_menu',
    sortSelect: '#sort-select',
    resetBtn: '#reset-all-chips'
  };

  // マジックナンバー排除
  const HEADER_HEIGHT = 66;      // アクティブチップの高さ
  const CLOSE_BTN_OFFSET = 12;   // ×ボタンの余白
  const ADJUST_WAIT = 100;       // リサイズ時のdebounce間隔


  const state = { touched: {} };
  const dom = {
    checkboxCache: {},
    sliders: {},
    sliderInputs: {},
    form: null,
    results: null,
    active: null,
    sidebar: null,
    layer: null,
    overlay: null,
    closeBtn: null,
    sliderToggle: null,
    sliderContainer: null,
    header: null,
    bottomNav: null,
    sortSelect: null,
    resetBtn: null
  };

  let scrollWatcher = null;
  let drawerResizeObserver = null;

  // ----- util -----
  const debounce = (fn,wait)=>{let t;return (...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),wait);};};

  function isHeaderVisible(){
    const header=dom.header;
    if(!header) return false;

    const rect=header.getBoundingClientRect();
    const style=getComputedStyle(header);

    const isVisible=
      header.offsetHeight>0&&
      style.visibility!=='hidden'&&
      parseFloat(style.opacity)>0&&
      rect.bottom>0;

    return isVisible;
  }

  // -----------------------------
  // レイアウト計算用のヘルパー関数
  // -----------------------------
  function getViewportHeight(){
    return window.visualViewport ? window.visualViewport.height : window.innerHeight;
  }
  function getHeaderHeight(){
    return (dom.header && isHeaderVisible()) ? dom.header.getBoundingClientRect().height : 0;
  }
  function getBottomNavHeight(){
    return dom.bottomNav ? dom.bottomNav.getBoundingClientRect().height : 0;
  }

  function syncOverlayWidth(){
    if(!dom.overlay || !dom.sidebar) return;
    const rect = dom.sidebar.getBoundingClientRect();
    const drawerWidth = rect && rect.width ? rect.width : 0;
    if(!drawerWidth){
      dom.overlay.style.width = '';
      return;
    }
    const viewportWidth = Math.max(window.innerWidth || document.documentElement.clientWidth || 0, 0);
    const clampedWidth = Math.min(drawerWidth, viewportWidth || drawerWidth);
    dom.overlay.style.width = `calc(100vw - ${clampedWidth}px)`;
  }

  function observeDrawerResize(){
    if(typeof ResizeObserver === 'undefined' || !dom.sidebar) return;
    if(drawerResizeObserver) return;
    drawerResizeObserver = new ResizeObserver(() => syncOverlayWidth());
    drawerResizeObserver.observe(dom.sidebar);
    syncOverlayWidth();
  }

  function cacheCheckboxes(){
    Object.keys(taxLabels).forEach(tax=>{
      dom.checkboxCache[tax]={};
      dom.form.querySelectorAll(`input[name="${tax}[]"]`).forEach(input=>{
        dom.checkboxCache[tax][input.value]={input,label:input.closest('label')};
      });
    });
  }

  // スライダー要素取得ヘルパー
  function getSliderParts(key){
    const el   = document.getElementById(`${key}-slider`);
    const minI = document.getElementById(`${key}_min_input`);
    const maxI = document.getElementById(`${key}_max_input`);
    const dMin = document.getElementById(`${key}-min`);
    const dMax = document.getElementById(`${key}-max`);
    return {el,minI,maxI,dMin,dMax};
  }

  // スライダー表示値の更新
  function updateSliderDisplay(key,min,max){
    const {minI,maxI,dMin,dMax}=getSliderParts(key);
    if(minI) minI.value=min;
    if(maxI) maxI.value=max;
    if(dMin) dMin.textContent=min;
    if(dMax) dMax.textContent=max;
  }

  // ----- slider 管理 -----
  const Slider = {
    init(){
      if (typeof window.noUiSlider === 'undefined') { console.error('noUiSlider missing'); return; }
      sliderKeys.forEach(key => {
        try {
          state.touched[key] = false;
          const parts = getSliderParts(key);
          const { el, minI, maxI } = parts;
          if (!el || !minI || !maxI) return;
          dom.sliders[key] = el;
          dom.sliderInputs[key] = { min: minI, max: maxI };
          const cfg = sliderConfig[key] || { min: 0, max: 10, step: 1 };
          if (el.noUiSlider && typeof el.noUiSlider.destroy === 'function') {
            el.noUiSlider.destroy();
          }
          noUiSlider.create(el, {
            start: [parseFloat(minI.value), parseFloat(maxI.value)],
            connect: true,
            range: { min: cfg.min, max: cfg.max },
            step: cfg.step,
            behaviour: 'none'
          });
          el.noUiSlider.on('update', vals => {
            let [vMin, vMax] = vals.map(parseFloat);
            if (key !== 'price') {
              vMin = Math.round(vMin);
              vMax = Math.round(vMax);
            }
            updateSliderDisplay(key, vMin, vMax);
          });
          el.noUiSlider.on('change', () => { state.touched[key] = true; fetchCounts(); });
        } catch (e) {
          /* slider init error を無視 */
        }
      });
    },
    updateRanges(ranges){
      if (!ranges) return;
      Object.entries(ranges).forEach(([key, info]) => {
        if (state.touched[key]) return;
        const el = dom.sliders[key];
        const inputs = dom.sliderInputs[key];
        if (!el || !inputs || info.min === undefined || info.max === undefined) return;
        let min = info.min;
        let max = info.max;
        if (key !== 'price') {
          min = Math.round(min);
          max = Math.round(max);
        }
        updateSliderDisplay(key, min, max);
        if (el.noUiSlider) el.noUiSlider.set([min, max]);
      });
    },
    // スライダーを初期状態に戻す
    reset(key){
      const cfg=sliderConfig[key];
      if(!cfg) return;
      state.touched[key]=false;
      updateSliderDisplay(key,cfg.min,cfg.max);
      const el=dom.sliders[key];
      if(el&&el.noUiSlider) el.noUiSlider.set([cfg.min,cfg.max]);
    }
  };

  // ----- form helpers -----
  function getFormData(){
    const fd=new FormData(dom.form);
    sliderKeys.forEach(k=>{if(!state.touched[k]){fd.delete(`${k}_min`);fd.delete(`${k}_max`);}});
    Object.entries(state.touched).forEach(([k,v])=>fd.append(`touched_${k}`,v?'1':'0'));
    const sort=dom.sortSelect;
    if(sort) fd.set('sort',sort.value);
    return fd;
  }

  // ----- ajax -----
  function fetchCounts(){
    const fd=getFormData();
    if(!AJAX_URL){ console.error('AJAX URL missing'); return; }
    fd.append('action','filter_term_counts');
    fetch(AJAX_URL,{method:'POST',body:fd})
      .then(r=>r.json())
      .then(updateCounts)
      .catch(()=>{});
  }

  function applyFilter(){
    const fd=getFormData();
    if(!AJAX_URL){ console.error('AJAX URL missing'); return; }
    fd.append('action','ajax_filter');
    fetch(AJAX_URL,{method:'POST',body:new URLSearchParams(fd)})
      .then(r=>r.json())
      .then(data=>{
        if(data.html) dom.results.innerHTML=data.html;
        Chips.update();
        fetchCounts();
      })
      .catch(()=>{});
  }

  function onFormSubmit(e){
    e.preventDefault();
    Sidebar.close();
    applyFilter();
  }

  // ----- update UI -----
  function updateCounts(data){
    Object.entries(data).forEach(([key,terms])=>{
      if(key==='sliders'){Slider.updateRanges(terms);return;}
      const cache=dom.checkboxCache[key]||{};
      (terms||[]).forEach(term=>{
        const c=cache[term.slug];if(!c) return;
        const {input,label}=c;
        const selected=input.checked;
        label.classList.toggle('filter-hidden',!selected&&term.count===0);
        let span=label.querySelector('.term-count');
        if(!span){span=document.createElement('span');span.className='term-count';label.appendChild(span);} 
        span.textContent=` (${term.count})`;
      });
    });
    updateGroupVisibility();
  }

  function updateGroupVisibility(){
    dom.form.querySelectorAll('.filter-group').forEach(g=>{
      const boxes=g.querySelectorAll('input[type="checkbox"]');
      if(boxes.length===0) return;
      const visible=Array.from(boxes).some(b=>!b.closest('label').classList.contains('filter-hidden'));
      g.style.display=visible?'':'none';
    });
  }

  // ----- チップ表示管理 -----
  const Chips = {
    update(){
      if (!dom.active) return;
      dom.active.innerHTML = '';
      const frag = document.createDocumentFragment();
      let has = false;
      Object.entries(taxLabels).forEach(([tax, label]) => {
        const cache = dom.checkboxCache[tax] || {};
        Object.values(cache).forEach(({ input, label: lab }) => {
          if (!input.checked) return;
          const slug = input.value;
          const name = lab.textContent.replace(/\s*\(.*?\)\s*$/, '').trim();
          const chip = document.createElement('span');
          chip.className = 'filter-chip';
          chip.dataset.type = tax;
          chip.dataset.value = slug;
          chip.innerHTML = `${label}: ${name} <button class="remove-chip" aria-label="remove">✕</button>`;
          frag.appendChild(chip);
          has = true;
        });
      });
      sliderKeys.forEach(k => {
        if (!state.touched[k]) return;
        const inputs = dom.sliderInputs[k];
        if (!inputs) return;
        const chip = document.createElement('span');
        chip.className = 'filter-chip';
        chip.dataset.type = k;
        chip.innerHTML = `${sliderLabels[k]}: ${inputs.min.value}〜${inputs.max.value} <button class="remove-chip" aria-label="remove">✕</button>`;
        frag.appendChild(chip);
        has = true;
      });
      dom.active.appendChild(frag);
      if (dom.resetBtn) dom.resetBtn.style.display = has ? '' : 'none';
      requestAnimationFrame(adjustFilterSidebarLayout);
    },
    bind(){
      if (!dom.active) return;
      dom.active.addEventListener('click', e => {
        const btn = e.target.closest('.remove-chip');
        if (!btn) return;
        const chip = btn.closest('.filter-chip');
        if (!chip) return;
        const type = chip.dataset.type;
        const val = chip.dataset.value;
        if (sliderKeys.includes(type)) {
          Slider.reset(type);
        } else {
          const cache = dom.checkboxCache[type] || {};
          const c = cache[val];
          if (c) c.input.checked = false;
        }
        Chips.update();
        applyFilter();
      });
    }
  };

  // ----- sidebar -----
  // サイドバーの高さや余白を動的計算
  function adjustFilterSidebarLayout(){
    const sidebarEl = dom.sidebar || document.getElementById('filter-sidebar');
    const sidebarInner = sidebarEl ? sidebarEl.querySelector('.filter-inner') : null;
    const actions = sidebarEl ? sidebarEl.querySelector('.filter-actions') : null;
    if(!sidebarInner) return;

    requestAnimationFrame(() => {
      const viewportHeight = getViewportHeight();
      const headerHeight   = getHeaderHeight();
      const bottomHeight   = getBottomNavHeight();
      const actionsHeight  = actions ? actions.getBoundingClientRect().height : 0;

      const hasChips = dom.active && dom.active.children.length > 0;
      const topPadding = headerHeight + (hasChips ? HEADER_HEIGHT : 0);

      sidebarInner.style.height = `${viewportHeight - actionsHeight}px`;
      sidebarInner.style.maxHeight = `${viewportHeight - bottomHeight - actionsHeight}px`;
      sidebarInner.style.paddingTop = `${topPadding}px`;
      sidebarInner.style.paddingBottom = `${bottomHeight}px`;
      if(actions) actions.style.bottom = `${bottomHeight}px`;
      sidebarInner.style.boxSizing = 'border-box';
      if(dom.closeBtn){
        dom.closeBtn.style.top = `${headerHeight + CLOSE_BTN_OFFSET}px`;
      }
      syncOverlayWidth();
    });
  }

  // ----- サイドバー制御 -----
  const Sidebar = {
    open(){
      const sidebar = dom.sidebar || document.getElementById('filter-sidebar');
      if(!sidebar){
        try{sessionStorage.setItem('openFilterOnLoad','1');}catch(e){}
        window.location.href = '/filter/';
        return;
      }
      dom.sidebar = sidebar;
      if(!dom.layer) dom.layer = document.querySelector(SELECTORS.layer);
      if(!dom.overlay) dom.overlay = document.querySelector(SELECTORS.overlay);
      adjustFilterSidebarLayout();
      observeDrawerResize();
      if(scrollWatcher){
        window.removeEventListener('scroll', scrollWatcher);
      }
      scrollWatcher = () => adjustFilterSidebarLayout();
      window.addEventListener('scroll', scrollWatcher, { passive: true });
      if(dom.layer) dom.layer.classList.add('is-open');
      document.body.classList.add('sidebar-open');
    },
    close(){
      if(dom.layer) dom.layer.classList.remove('is-open');
      document.body.classList.remove('sidebar-open');
      if(scrollWatcher){
        window.removeEventListener('scroll', scrollWatcher);
        scrollWatcher = null;
      }
    },
    toggle(){
      const layerEl = dom.layer || document.querySelector(SELECTORS.layer);
      dom.layer = layerEl;
      if(layerEl && layerEl.classList.contains('is-open')){
        Sidebar.close();
      }else{
        Sidebar.open();
      }
    },
    bind(){
      const overlayEl = dom.overlay || document.querySelector(SELECTORS.overlay);
      const drawerEl  = dom.sidebar || document.querySelector(SELECTORS.sidebar);
      if(overlayEl){
        dom.overlay = overlayEl;
        overlayEl.addEventListener('click',e=>{e.stopPropagation();Sidebar.close();});
      }
      if(drawerEl){
        dom.sidebar = drawerEl;
        drawerEl.addEventListener('click',e=>{e.stopPropagation();});
      }
      if(dom.closeBtn){
        dom.closeBtn.addEventListener('click',e=>{e.preventDefault();Sidebar.close();});
      }
      document.querySelectorAll('.filter-toggle-btn,[data-onclick="toggleFilter"]').forEach(btn=>{
        btn.addEventListener('click',e=>{e.preventDefault();Sidebar.toggle();});
        btn.addEventListener('touchstart',e=>{e.preventDefault();Sidebar.toggle();});
      });
      document.addEventListener('keydown',e=>{
        if(e.key==='Escape'){
          const layerNode = dom.layer || document.querySelector(SELECTORS.layer);
          if(layerNode && layerNode.classList.contains('is-open')){
            Sidebar.close();
          }
        }
      });
    }
  };

  function bindSliderToggle(){
    if(!dom.sliderToggle||!dom.sliderContainer) return;
    dom.sliderToggle.addEventListener('click',()=>{
      const hidden=dom.sliderContainer.style.display==='none';
      dom.sliderContainer.style.display=hidden?'block':'none';
      dom.sliderToggle.textContent=hidden?'\u25B2 スライダーフィルターを非表示':'\u25BC スライダーフィルターを表示';
    });
  }

  // ----- events -----
  // 各種イベントバインド
  function bindEvents(){
    // 入力値変更で件数のみ更新（debounce）
    dom.form.addEventListener('input', debounce(fetchCounts,300));
    dom.form.addEventListener('submit', onFormSubmit);
    // チェックが外れたら即再検索
    dom.form.addEventListener('change',e=>{
      const t=e.target;
      if(t&&t.matches('input[type="checkbox"]')&&!t.checked){
        Chips.update();
        applyFilter();
      }
    });
    // 並び替え
    const sort=dom.sortSelect;
    if(sort) sort.addEventListener('change',()=>{
      const url=new URL(window.location);
      url.searchParams.set('sort',sort.value);
      window.history.replaceState({},'',url);
      applyFilter();
    });
    // リセット
    const reset=dom.resetBtn;
    if(reset) reset.addEventListener('click',()=>{window.location.href=window.location.href.split('?')[0];});
    Chips.bind();
    Sidebar.bind();
  }

  // ----- init -----
  function init(){
    dom.form=document.querySelector(SELECTORS.form);
    dom.results=document.querySelector(SELECTORS.results);
    dom.active=document.querySelector(SELECTORS.active);
    dom.sidebar=document.querySelector(SELECTORS.sidebar);
    dom.layer=document.querySelector(SELECTORS.layer);
    dom.overlay=document.querySelector(SELECTORS.overlay);
    dom.closeBtn=document.querySelector(SELECTORS.closeBtn);
    dom.sliderToggle=document.querySelector(SELECTORS.sliderToggle);
    dom.sliderContainer=document.querySelector(SELECTORS.sliderContainer);
    dom.header=document.querySelector(SELECTORS.header);
    dom.bottomNav=document.querySelector(SELECTORS.bottomNav);
    dom.sortSelect=document.querySelector(SELECTORS.sortSelect);
    dom.resetBtn=document.querySelector(SELECTORS.resetBtn);
    try{
      if(sessionStorage.getItem('openFilterOnLoad')==='1'){
        sessionStorage.removeItem('openFilterOnLoad');
        requestAnimationFrame(()=>Sidebar.open());
      }
    }catch(e){}
    adjustFilterSidebarLayout();
    const debouncedAdjust = debounce(adjustFilterSidebarLayout, ADJUST_WAIT);
    window.addEventListener('resize', debouncedAdjust);
    window.addEventListener('orientationchange', debouncedAdjust);
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', debouncedAdjust);
    }
    if(!dom.form||!dom.results){
      Sidebar.bind();
      return;
    }
    cacheCheckboxes();
    try{Slider.init();}catch(e){}
    bindEvents();
    bindSliderToggle();
    fetchCounts();
    document.querySelectorAll('.hidden-on-load').forEach(el=>el.classList.remove('hidden-on-load'));
    Chips.update();
  }

  document.addEventListener('DOMContentLoaded', init);
  window.FilterUI = { init, openSidebar: Sidebar.open, closeSidebar: Sidebar.close, toggleSidebar: Sidebar.toggle };
})();
