(function(){
  if (window.__coaiPwInitFile) return;
  window.__coaiPwInitFile = true;

  function ensureBelowButton(input){
    if (!input || input.dataset.coaiPwEnhanced === '1') return;

    var wrap = input.closest ? input.closest('.coai-pwwrap') : null;
    if (!wrap) {
      // if markup didn't wrap, create a wrapper so the button can live underneath
      wrap = document.createElement('div');
      wrap.className = 'coai-pwwrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
    }

   var btn = wrap.querySelector('.coai-pwbtn');
  // inside ensureBelowButton(input) where you create `btn`:
  if (!btn) {
    btn = document.createElement('span');              // span avoids theme button CSS
    btn.className = 'coai-pwbtn';
    btn.setAttribute('role','button');
    btn.setAttribute('tabindex','0');

    // compact, theme-proof
    btn.style.cssText = [
      'display:inline-flex','align-items:center','gap:.35rem',
      'margin-top:.25rem','float:right',
      'padding:.18rem .5rem',
      'font-size:.78rem','line-height:1',
      'background:#f3f4f6','border:1px solid #d1d5db','border-radius:6px',
      'cursor:pointer'
    ].join(';');

    // --- persistent eye icon (SVG) ---
    var NS = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox','0 0 24 24');
    svg.setAttribute('width','14');
    svg.setAttribute('height','14');
    svg.setAttribute('aria-hidden','true');
    svg.style.display = 'block';
    svg.style.stroke = 'currentColor';
    svg.style.fill   = 'none';

    var g  = document.createElementNS(NS,'g');
    g.setAttribute('stroke-linecap','round');
    g.setAttribute('stroke-linejoin','round');
    g.setAttribute('stroke-width','2');
    var p1 = document.createElementNS(NS,'path');
    p1.setAttribute('d','M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z');
    var c  = document.createElementNS(NS,'circle');
    c.setAttribute('cx','12'); c.setAttribute('cy','12'); c.setAttribute('r','3');
    g.appendChild(p1); g.appendChild(c); svg.appendChild(g);

    // --- label span ---
    var label = document.createElement('span');
    label.className = 'coai-pwlabel';
    label.textContent = 'Show password';

    // attach both, keep refs
    btn._icon  = svg;
    btn._label = label;
    btn.appendChild(svg);
    btn.appendChild(label);

    wrap.appendChild(btn);
  }


    function toggle(){
      var isPw = input.type === 'password';
      try { input.type = isPw ? 'text' : 'password'; } catch(e){}
      // update label only; icon stays
      btn._label.textContent = isPw ? 'Hide password' : 'Show password';
      input.focus();
    }


    // idempotent wiring
    if (btn.__coaiClick) btn.removeEventListener('click', btn.__coaiClick);
    if (btn.__coaiKey)   btn.removeEventListener('keydown',btn.__coaiKey);
    
    btn.__coaiClick = function () { toggle(); };
    btn.__coaiKey = function(e){ 
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } 
        
    };
    
    btn.addEventListener('click', btn.__coaiClick, { passive: true});
    btn.addEventListener('keydown', btn.__coaiKey);

    input.dataset.coaiPwEnhanced = '1';
  }

  function enhanceAll(root){
    var list = (root.querySelectorAll ? root.querySelectorAll('input[type="password"]') : []);
    for (var i=0;i<list.length;i++) ensureBelowButton(list[i]);
  }

  // initial pass
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ enhanceAll(document); }, {once:true});
  } else {
    enhanceAll(document);
  }

  // dynamic fields (no MutationObserver headaches)
  document.addEventListener('focusin', function(e){
    var t = e.target;
    if (t && t.tagName === 'INPUT' && t.type === 'password') {
      ensureBelowButton(t);
    }
  }, false);
})();
