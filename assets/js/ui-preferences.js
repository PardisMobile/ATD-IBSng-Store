(() => {
  'use strict';
  const root = document.documentElement;
  const storage = { mode: 'atd-ui-mode', accent: 'atd-ui-accent', lang: 'atd-ui-language', legacyLang: 'atd-ibsng-language' };
  const accents = {
    blue: ['#2563eb','#7aa7ff'], violet: ['#7c3aed','#b69aff'],
    teal: ['#0d9488','#42d6b0'], rose: ['#e11d48','#fb8aa4'],
    amber: ['#b45309','#fbbf24']
  };
  const icons = {
    sun:'<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
    moon:'<path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/>',
    palette:'<path d="M12 3a9 9 0 1 0 0 18h1.2a1.8 1.8 0 0 0 1.3-3.1 1.8 1.8 0 0 1 1.3-3.1H18a3 3 0 0 0 3-3 9 9 0 0 0-9-8Z"/><path d="M7.5 10h.01M10 6.5h.01M15 7.5h.01M17 10.5h.01"/>',
    globe:'<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>'
  };
  const svg = (name) => '<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'+(icons[name]||'')+'</svg>';
  const get = (k, fallback) => { try { return localStorage.getItem(k) || fallback; } catch (_) { return fallback; } };
  const set = (k,v) => { try { localStorage.setItem(k,v); } catch (_) {} };
  function apply() {
    const mode = get(storage.mode, 'dark'), accent = get(storage.accent, 'blue');
    root.dataset.theme = mode === 'light' ? 'light' : 'dark';
    root.dataset.mode = root.dataset.theme;
    const language = get(storage.lang, get(storage.legacyLang, root.lang || 'fa'));
    root.lang = language === 'en' ? 'en' : 'fa';
    root.dir = language === 'en' ? 'ltr' : 'rtl';
    root.dataset.language = root.lang;
    root.dataset.accent = accents[accent] ? accent : 'blue';
    root.style.colorScheme = root.dataset.theme;
    const colors = accents[root.dataset.accent];
    root.style.setProperty('--accent-light', colors[0]);
    root.style.setProperty('--accent-dark', colors[1]);
    root.style.setProperty('--accent', mode === 'light' ? colors[0] : colors[1]);
  }
  function mount() {
    if (document.querySelector('.atd-preferences')) return;
    const controls = document.createElement('div');
    controls.className = 'atd-preferences';
    controls.setAttribute('aria-label','Appearance and language');
    controls.innerHTML =
      '<button type="button" class="atd-pref-mode" aria-label="Toggle theme" aria-pressed="'+(get(storage.mode,'dark')==='light')+'" title="Light / dark">'+svg(get(storage.mode,'dark')==='dark'?'sun':'moon')+'</button>'+
      '<button type="button" class="atd-pref-palette" aria-label="Choose color scheme" title="Color scheme">'+svg('palette')+'</button>'+
      '<button type="button" class="atd-pref-language" aria-label="'+(get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'Switch language to English':'تغییر زبان به فارسی')+'" title="Language">'+svg('globe')+'<span>'+(get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'EN':'فا')+'</span></button>';
    const palette = document.createElement('div');
    palette.className='atd-palette-popover'; palette.hidden=true;
    palette.id='atd-accent-palette';
    palette.setAttribute('role','group'); palette.setAttribute('aria-label','Color scheme');
    Object.entries(accents).forEach(([name,colors])=>{
      const b=document.createElement('button'); b.type='button'; b.className='atd-swatch'; b.dataset.accent=name;
      b.style.setProperty('--swatch',colors[0]); b.setAttribute('aria-label',name); b.title=name;
      b.innerHTML='<span></span>'; b.addEventListener('click',()=>{set(storage.accent,name);apply();palette.querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));});
      b.setAttribute('aria-pressed',String(get(storage.accent,'blue')===name)); palette.appendChild(b);
    });
    controls.appendChild(palette);
    controls.querySelector('.atd-pref-mode').addEventListener('click',()=>{
      const next=get(storage.mode,'dark')==='dark'?'light':'dark';set(storage.mode,next);apply();
      const modeButton=controls.querySelector('.atd-pref-mode');
      modeButton.innerHTML=svg(next==='dark'?'sun':'moon');
      modeButton.setAttribute('aria-pressed',String(next==='light'));
    });
    const paletteButton=controls.querySelector('.atd-pref-palette');
    paletteButton.setAttribute('aria-controls',palette.id);
    paletteButton.setAttribute('aria-expanded','false');
    const closePalette=()=>{palette.hidden=true;paletteButton.setAttribute('aria-expanded','false');};
    paletteButton.addEventListener('click',()=>{palette.hidden=!palette.hidden;paletteButton.setAttribute('aria-expanded',String(!palette.hidden));});
    controls.querySelector('.atd-pref-language').addEventListener('click',()=>{
      const next=(get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'en':'fa');set(storage.lang,next);set(storage.legacyLang,next);apply();
      controls.querySelector('.atd-pref-language span').textContent=next==='fa'?'EN':'فا';
      controls.querySelector('.atd-pref-language').setAttribute('aria-label',next==='fa'?'Switch language to English':'تغییر زبان به فارسی');
      root.dispatchEvent(new CustomEvent('atd:language-change',{detail:{language:next,direction:root.dir}}));
      document.dispatchEvent(new CustomEvent('atd:language-change',{detail:{language:next,direction:root.dir}}));
    });
    const host=document.querySelector('.topbar,.admin-top,.store-header,.admin-header')||document.body;
    host.appendChild(controls);
    document.addEventListener('pointerdown',(event)=>{
      if(!controls.contains(event.target)) closePalette();
    });
    document.addEventListener('keydown',(event)=>{
      if(event.key==='Escape') closePalette();
    });
  }
  apply();
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();