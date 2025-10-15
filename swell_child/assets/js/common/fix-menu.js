(function(){
  const nav = document.getElementById('fix_bottom_menu');
  if(!nav) return;

  const root = document.documentElement;
  const body = document.body;

  function setBodyPadding(){
    const h = Math.ceil(nav.getBoundingClientRect().height);
    root.style.setProperty('--bottom-nav-h', h + 'px');
    body.classList.add('has-bottom-nav');
  }

  function handleViewportOcclusion(){
    const vv = window.visualViewport;
    if(!vv){
      setBodyPadding();
      return;
    }
    const ratio = vv.height / window.innerHeight;
    const occluded = ratio < 0.75; // heuristic
    nav.classList.toggle('is-occluded', occluded);
    setBodyPadding();
  }

  setBodyPadding();
  window.addEventListener('resize', setBodyPadding, {passive:true});
  window.addEventListener('orientationchange', setBodyPadding);

  if(window.visualViewport){
    const vv = window.visualViewport;
    vv.addEventListener('resize', handleViewportOcclusion, {passive:true});
    vv.addEventListener('scroll', handleViewportOcclusion, {passive:true});
  }

  if(document.fonts && document.fonts.ready){
    document.fonts.ready.then(setBodyPadding).catch(()=>{});
  }
  window.addEventListener('load', setBodyPadding, {once:true});
})();
