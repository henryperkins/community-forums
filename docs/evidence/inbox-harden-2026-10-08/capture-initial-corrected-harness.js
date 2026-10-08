async page => {
  const base='http://127.0.0.1:8484';
  const engine=page.context().browser().browserType().name();
  const out='/home/ubuntu/community-forums/docs/evidence/inbox-harden-2026-10-08';
  page.setDefaultTimeout(4000); page.setDefaultNavigationTimeout(5000);
  const cases=[],errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  const record=async (name,run)=>{try{cases.push({name,...await run()});}catch(e){cases.push({name,harnessError:e.message});await page.unroute('**/inbox/preview/*');}};
  const state=()=>page.evaluate(()=>({url:location.pathname+location.search,busy:document.querySelector('[data-inbox-reading]')?.getAttribute('aria-busy'),rows:document.querySelectorAll('[data-inbox-row]').length,empty:document.querySelector('.inbox-empty-state')?.textContent.trim()||null,master:!!document.querySelector('[data-inbox-select-all]'),shown:document.querySelector('.inbox-shown-count')?.textContent,focus:document.activeElement?.tagName,focusText:document.activeElement?.textContent.slice(0,60),documentWidth:document.documentElement.clientWidth,scrollWidth:document.documentElement.scrollWidth}));
  await page.goto(base+'/inbox?scope=starred');
  const assets=await page.evaluate(async()=>Promise.all([...document.querySelectorAll('link[rel="stylesheet"],script[src]')].map(n=>n.href||n.src).filter(u=>/app-style-|app-[0-9a-f]+\.js/.test(u)).map(async u=>{const r=await fetch(u);const b=await r.arrayBuffer();return{url:new URL(u).pathname,status:r.status,sha256:[...new Uint8Array(await crypto.subtle.digest('SHA-256',b))].map(v=>v.toString(16).padStart(2,'0')).join('')};})));
  await record('last-unread',async()=>{
    await page.setViewportSize({width:393,height:844});
    await page.goto(base+'/inbox?scope=unread&order=active');
    const before=await state();
    await page.locator('[data-inbox-preview-url]').first().click();
    await page.locator('[data-inbox-preview]').waitFor();
    await page.getByRole('button',{name:'Back to topics',exact:true}).click();
    await page.waitForFunction(()=>!document.querySelector('[data-inbox-list]').classList.contains('is-hidden'));
    const after=await state();
    await page.screenshot({path:out+'/'+engine+'-initial-last-unread.png'});
    return{before,after,semantic:await page.locator('[data-inbox-list]').ariaSnapshot()};
  });
  for(const mode of ['500','network-abort','malformed']) await record('preview-'+mode,async()=>{
    await page.setViewportSize({width:1440,height:900});
    await page.goto(base+'/inbox?scope=starred&order=active');
    await page.route('**/inbox/preview/*',r=>mode==='network-abort'?r.abort('failed'):r.fulfill({status:mode==='500'?500:200,contentType:'text/html',body:mode==='500'?'Service unavailable':'<p>Unexpected response</p>'}));
    await page.locator('[data-inbox-preview-url]').first().click();
    await page.waitForURL(/\/t\/\d/,{timeout:3000});
    const result=await state();
    await page.screenshot({path:out+'/'+engine+'-initial-'+mode+'.png'});
    await page.unroute('**/inbox/preview/*');
    return result;
  });
  await record('preview-hung',async()=>{
    await page.goto(base+'/inbox?scope=starred&order=active');
    let held;
    await page.route('**/inbox/preview/*',r=>{held=r;});
    await page.locator('[data-inbox-preview-url]').first().click();
    await page.waitForTimeout(1500);
    const result=await state();
    result.readingText=await page.locator('[data-inbox-reading]').innerText();
    await page.screenshot({path:out+'/'+engine+'-initial-hung.png'});
    await held.abort();
    await page.unroute('**/inbox/preview/*');
    await page.goto(base+'/inbox?scope=starred&order=active');
    return result;
  });
  await record('rapid-preview-race',async()=>{
    await page.goto(base+'/inbox?scope=starred&order=active');
    const links=page.locator('[data-inbox-preview-url]');
    const ids=await links.evaluateAll(ns=>ns.slice(0,2).map(n=>n.closest('[data-inbox-row]').dataset.threadId));
    await page.route('**/inbox/preview/*',async r=>{const id=new URL(r.request().url()).pathname.split('/').pop();const response=await r.fetch();if(id===ids[0])await new Promise(resolve=>setTimeout(resolve,300));await r.fulfill({response});});
    await links.nth(0).click();
    await links.nth(1).click();
    await page.waitForTimeout(500);
    const current=await page.locator('[data-inbox-preview]').getAttribute('data-inbox-preview');
    const heading=await page.locator('[data-inbox-preview] > header > h2').innerText();
    await page.unroute('**/inbox/preview/*');
    return{ids,current,heading,active:await page.locator('[data-inbox-row].is-active').getAttribute('data-thread-id'),...await state()};
  });
  await record('rtl-cjk-long-title',async()=>{
    await page.setViewportSize({width:320,height:650});
    await page.goto(base+'/inbox?scope=starred&order=active');
    await page.evaluate(()=>document.documentElement.dir='rtl');
    const row=page.locator('[data-inbox-row][data-thread-id="3"]');
    await row.scrollIntoViewIfNeeded();
    const geometry=await row.evaluate(n=>{const title=n.querySelector('.thread-title');return{row:n.getBoundingClientRect().toJSON(),title:title.getBoundingClientRect().toJSON(),titleScroll:title.scrollWidth,titleClient:title.clientWidth};});
    await row.locator('[data-inbox-row-menu] > summary').click();
    await page.waitForFunction(()=>document.querySelector('[data-inbox-row-menu][open]').hasAttribute('data-inbox-menu-positioned'));
    const menu=await row.locator('.thread-row-menu-panel').boundingBox();
    await page.screenshot({path:out+'/'+engine+'-initial-rtl-cjk.png'});
    return{geometry,menu,...await state()};
  });
  await record('short-large-text',async()=>{
    await page.setViewportSize({width:320,height:180});
    await page.goto(base+'/inbox?scope=starred&order=active');
    await page.evaluate(()=>document.documentElement.style.fontSize='200%');
    const output=[];
    for(const selector of ['.create-menu','.inbox-compact-menu','.inbox-actions']){
      await page.locator(selector+' > summary').evaluate(n=>n.click());
      await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
      const panel=page.locator(selector+' > :is(.create-menu-panel,.inbox-menu-panel)');
      output.push({selector,open:await page.locator(selector).evaluate(n=>n.open),positioned:await page.locator(selector).getAttribute('data-inbox-menu-positioned'),box:await panel.boundingBox(),styles:await panel.evaluate(n=>({maxHeight:getComputedStyle(n).maxHeight,overflow:getComputedStyle(n).overflowY,scroll:n.scrollHeight,client:n.clientHeight}))});
      await page.screenshot({path:out+'/'+engine+'-initial-short-'+selector.slice(1)+'.png'});
      await page.keyboard.press('Escape');
    }
    await page.locator('.inbox-actions > summary').click();
    await page.getByRole('button',{name:'Help',exact:true}).evaluate(n=>n.click());
    const help=await page.locator('.inbox-help-dialog').boundingBox();
    await page.screenshot({path:out+'/'+engine+'-initial-short-help.png'});
    return{menus:output,help,...await state()};
  });
  await record('forced-colors-switch',async()=>{
    await page.setViewportSize({width:393,height:844});
    await page.emulateMedia({forcedColors:'active'});
    await page.goto(base+'/inbox?scope=starred&order=active');
    const menu=page.locator('[data-inbox-row-menu]').first();
    await menu.locator(':scope > summary').click();
    await page.waitForFunction(()=>document.querySelector('[data-inbox-row-menu][open]').hasAttribute('data-inbox-menu-positioned'));
    const switchState=await menu.locator('[role="switch"]').evaluate(n=>({checked:n.getAttribute('aria-checked'),forced:matchMedia('(forced-colors: active)').matches,track:((s)=>({background:s.backgroundColor,color:s.color,border:s.borderColor}))(getComputedStyle(n.querySelector('.inbox-switch-track'))),thumb:((s)=>({background:s.backgroundColor,border:s.borderColor,borderWidth:s.borderWidth}))(getComputedStyle(n.querySelector('.inbox-switch-thumb')))}));
    await page.screenshot({path:out+'/'+engine+'-initial-forced-colors.png'});
    await page.emulateMedia({forcedColors:'none'});
    return{switchState,...await state()};
  });
  return{engine,assets,cases,errors,build:'9cd60f8b2665c07a',limitations:['Linux Chromium/WebKit emulation; no physical Safari/Windows forced-colors','200% root text size emulates text scaling, not browser zoom','Hung request observed for 1.5 seconds plus static no-timeout confirmation']};
}
