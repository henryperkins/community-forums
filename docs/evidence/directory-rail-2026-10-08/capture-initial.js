async page => {
 const base='http://127.0.0.1:8484',out='/home/ubuntu/community-forums/docs/evidence/directory-rail-2026-10-08',engine=page.context().browser().browserType().name();
 page.setDefaultTimeout(4000);page.setDefaultNavigationTimeout(5000);const cases=[],errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/?pane=connections');
 const assets=await page.evaluate(async()=>Promise.all([...document.querySelectorAll('link[rel="stylesheet"],script[src]')].map(n=>n.href||n.src).filter(u=>/app-style-|app-[0-9a-f]+\.js/.test(u)).map(async u=>{const r=await fetch(u),b=await r.arrayBuffer();return{url:new URL(u).pathname,status:r.status,sha256:[...new Uint8Array(await crypto.subtle.digest('SHA-256',b))].map(v=>v.toString(16).padStart(2,'0')).join('')};})));
 for(const width of [1440,393,320])for(const pane of ['boards','tags','connections']){
  await page.setViewportSize({width,height:width===1440?900:844});await page.goto(base+'/?pane='+pane);await page.evaluate(()=>document.fonts.ready);
  const metrics=await page.evaluate(()=>{const rect=n=>n?n.getBoundingClientRect().toJSON():null;return{url:location.pathname+location.search,client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,subheader:rect(document.querySelector('[data-subheader]')),leading:rect(document.querySelector('.forum-subheader-leading')),tabs:rect(document.querySelector('.forum-directory__tabs')),creation:rect(document.querySelector('[data-create-trigger]')),tabLinks:[...document.querySelectorAll('.forum-directory__tabs a')].map(n=>({text:n.textContent.trim(),href:n.getAttribute('href'),current:n.getAttribute('aria-current'),rect:rect(n)})),railGroups:[...document.querySelectorAll('[data-sidebar] .board-rail-cat')].map(n=>({text:n.textContent,rect:rect(n)})),railLinks:[...document.querySelectorAll('[data-sidebar] a, [data-sidebar] button')].map(n=>({text:n.textContent.trim(),href:n.getAttribute('href'),current:n.getAttribute('aria-current'),rect:rect(n)})),heading:document.querySelector('main h1')?.textContent};});
  await page.screenshot({path:out+'/'+engine+'-initial-'+width+'-'+pane+'.png'});
  let drawer=null;
  if(width!==1440){
   await page.getByRole('button',{name:'Open board rail',exact:true}).click();
   drawer=await page.evaluate(()=>({open:document.body.classList.contains('nav-open'),mainInert:document.querySelector('main').inert,focused:document.activeElement?.getAttribute('aria-label'),firstLinks:[...document.querySelectorAll('[data-sidebar] a[href],[data-sidebar] button')].filter(n=>n.getClientRects().length).slice(0,8).map(n=>({text:n.textContent.trim(),name:n.getAttribute('aria-label'),href:n.getAttribute('href'),height:n.getBoundingClientRect().height})),rail:document.querySelector('[data-sidebar]').getBoundingClientRect().toJSON()}));
   await page.screenshot({path:out+'/'+engine+'-initial-'+width+'-'+pane+'-drawer.png'});
   await page.keyboard.press('Escape');drawer.focusRestored=await page.getByRole('button',{name:'Open board rail',exact:true}).evaluate(n=>n===document.activeElement);
  }
  cases.push({width,pane,metrics,drawer});
 }
 await page.setViewportSize({width:320,height:568});await page.goto(base+'/?pane=connections');await page.evaluate(()=>document.documentElement.style.fontSize='200%');
 const large=await page.evaluate(()=>({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,subheader:document.querySelector('[data-subheader]').getBoundingClientRect().toJSON(),creation:document.querySelector('[data-create-trigger]').getBoundingClientRect().toJSON(),tabs:document.querySelector('.forum-directory__tabs').getBoundingClientRect().toJSON()}));
 await page.getByRole('button',{name:'Open board rail',exact:true}).click();await page.screenshot({path:out+'/'+engine+'-initial-320-large-drawer.png'});await page.keyboard.press('Escape');
 const order=await page.evaluate(()=>[...document.querySelectorAll('a[href],button,input,select,textarea,summary,[tabindex="0"]')].filter(n=>n.getClientRects().length&&!n.closest('details:not([open])')).map(n=>({tag:n.tagName,name:n.getAttribute('aria-label')||n.textContent.trim().slice(0,50),href:n.getAttribute('href'),inRail:!!n.closest('[data-sidebar]')})).slice(0,45));
 return{engine,assets,cases,large,order,errors,limitations:['Linux Chromium layout evidence only for initial assessment; final Chromium/WebKit confirmation follows root correction batch','200% root font sizing, not physical browser zoom','No detector score used']};
}
