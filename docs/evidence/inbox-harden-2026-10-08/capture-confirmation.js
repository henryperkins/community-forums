async page => {
  const base='http://127.0.0.1:8484',build='e26e139ee9899a8f';
  const engine=page.context().browser().browserType().name();
  const out='/home/ubuntu/community-forums/docs/evidence/inbox-harden-2026-10-08';
  page.setDefaultTimeout(4000);page.setDefaultNavigationTimeout(5000);
  const cases=[],failures=[],errors=[];let checks=0;
  page.on('pageerror',e=>errors.push(e.message));
  const check=(ok,label,detail)=>{checks++;if(!ok)failures.push({label,detail});};
  const record=async(name,run)=>{try{cases.push({name,...await run()});}catch(e){failures.push({label:name+' harness',detail:e.message});cases.push({name,harnessError:e.message});}finally{await page.unroute('**/inbox/preview/*');await page.context().setOffline(false);}};
  const state=async(p=page)=>p.evaluate(()=>({url:location.pathname+location.search,busy:document.querySelector('[data-inbox-reading]')?.getAttribute('aria-busy'),rows:document.querySelectorAll('[data-inbox-row]').length,empty:document.querySelector('[data-inbox-empty-state]')?.textContent.trim()||null,focus:document.activeElement?.tagName,focusEmpty:document.activeElement?.hasAttribute('data-inbox-empty-state'),status:document.querySelector('[data-inbox-status]')?.textContent,documentWidth:document.documentElement.clientWidth,scrollWidth:document.documentElement.scrollWidth}));
  const recovery=async(p=page)=>{await p.locator('[data-inbox-recovery]').waitFor({state:'visible'});return await state(p);};
  await page.setViewportSize({width:1440,height:900});await page.goto(base+'/inbox?scope=starred&order=active');
  const assets=await page.evaluate(async()=>Promise.all([...document.querySelectorAll('link[rel="stylesheet"],script[src]')].map(n=>n.href||n.src).filter(u=>/app-style-|app-[0-9a-f]+\.js/.test(u)).map(async u=>{const r=await fetch(u),b=await r.arrayBuffer();return{url:new URL(u).pathname,status:r.status,sha256:[...new Uint8Array(await crypto.subtle.digest('SHA-256',b))].map(v=>v.toString(16).padStart(2,'0')).join('')};})));
  check(assets.some(a=>a.sha256==='3bc29160425933ecf16bfc4ca8c791e0af8ad153dc96875bad9858026a078e88'),'final JS hash',assets);
  check(assets.some(a=>a.sha256==='b4cc42697751c97248b49185d8a7d249249f1c18c1f3604b8b1de28d0efaa320'),'final CSS hash',assets);
  await record('sole-unread-caught-up',async()=>{
    await page.setViewportSize({width:393,height:844});await page.goto(base+'/inbox?scope=unread&order=active');
    check(await page.locator('[data-inbox-row]').count()===1,'sole unread fixture');
    await page.locator('[data-inbox-preview-url]').first().click();await page.locator('[data-inbox-preview]').waitFor();
    check((await page.locator('[data-inbox-status]').innerText()).includes('caught up'),'caught-up status announced');
    await page.getByRole('button',{name:'Back to topics',exact:true}).click();
    await page.locator('[data-inbox-empty-state]').waitFor({state:'visible'});
    const result=await state();check(result.empty.includes('caught up')&&result.focusEmpty,'caught-up state takes Back focus',result);
    check(!await page.locator('[data-inbox-select-all]').isVisible(),'drained master hidden');
    check(!await page.locator('[data-inbox-thread-list]').isVisible(),'drained empty list hidden');
    check(!await page.locator('.inbox-shown-count').isVisible(),'obsolete zero footer hidden');
    check(await page.getByRole('link',{name:'Back to For You',exact:true}).isVisible(),'empty state recovery available');
    await page.screenshot({path:out+'/'+engine+'-final-caught-up.png'});return result;
  });
  for(const mode of ['500','400','429','network','offline','malformed','wrong-topic']) await record('preview-'+mode,async()=>{
    await page.setViewportSize({width:mode==='offline'?393:1440,height:900});await page.goto(base+'/inbox?scope=starred&order=active');
    const before=await state();const link=page.locator('[data-inbox-preview-url]').first(),canonical=await link.getAttribute('href');
    if(mode==='offline')await page.context().setOffline(true);
    else await page.route('**/inbox/preview/*',r=>mode==='network'?r.abort('failed'):r.fulfill({status:/^\d+$/.test(mode)?Number(mode):200,contentType:'text/html',body:mode==='wrong-topic'?'<article data-inbox-preview="999999"><h2>Wrong topic</h2></article>':'<p>Unavailable</p>'}));
    await link.click();const result=await recovery();
    check(result.url===before.url,'queue retained '+mode,result);check(result.busy===null,'busy clears '+mode,result);
    check(result.rows===before.rows,'rows retained '+mode,result);
    check(await page.locator('[data-inbox-full-topic]').getAttribute('href')===canonical,'native full topic points to intended '+mode);
    if(mode==='429')check(result.status.includes('Too many requests'),'rate limit guidance',result.status);
    if(mode==='offline')check(result.status.includes('offline'),'offline guidance',result.status);
    check(await page.locator('[data-inbox-preview="999999"]').count()===0,'invalid fragment not injected '+mode);
    if(mode==='500')await page.screenshot({path:out+'/'+engine+'-final-preview-recovery.png'});
    return result;
  });
  await record('retry-and-native-topic-link',async()=>{
    await page.setViewportSize({width:1440,height:900});await page.goto(base+'/inbox?scope=starred&order=active');
    let fail=true;await page.route('**/inbox/preview/*',r=>fail?r.fulfill({status:500,body:'unavailable'}):r.continue());
    const link=page.locator('[data-inbox-preview-url]').first(),id=await link.evaluate(n=>n.closest('[data-inbox-row]').dataset.threadId);
    await link.click();await recovery();await page.getByRole('button',{name:'Try again',exact:true}).click();await recovery();check(await page.locator('[data-inbox-retry]').evaluate(n=>n===document.activeElement),'failed retry restores its own focus');fail=false;await page.getByRole('button',{name:'Try again',exact:true}).click();
    await page.locator('[data-inbox-preview="'+id+'"]').waitFor();
    check(!await page.locator('[data-inbox-recovery]').isVisible(),'retry clears recovery');
    check(await page.locator('[data-inbox-reading]').getAttribute('aria-busy')===null,'retry clears busy');
    await page.goto(base+'/inbox?scope=starred&order=active');fail=true;await page.locator('[data-inbox-preview-url]').first().click();await recovery();
    const expected=await page.locator('[data-inbox-full-topic]').getAttribute('href');
    await page.getByRole('link',{name:'Open full topic',exact:true}).click();await page.waitForURL(u=>u.pathname===expected);
    check(new URL(page.url()).pathname===expected,'deliberate full topic navigation');return{expected,actual:new URL(page.url()).pathname};
  });
  await record('preserved-existing-reply-draft',async()=>{
    await page.setViewportSize({width:1440,height:900});await page.goto(base+'/inbox?scope=starred&order=active');
    await page.locator('[data-inbox-row][data-thread-id="3"] [data-inbox-preview-url]').click();
    await page.locator('[data-inbox-preview="3"]').waitFor();
    const draft=page.locator('[data-inbox-preview="3"] textarea[name="body"]');
    await draft.fill('Unsaved reply محفوظ 🪴');const marker=await draft.evaluate(n=>{n.dataset.hardenDraft='retained';return n.dataset.hardenDraft;});
    await page.route('**/inbox/preview/*',r=>r.fulfill({status:500,body:'unavailable'}));
    await page.locator('[data-inbox-row][data-thread-id="4"] [data-inbox-preview-url]').click();await recovery();
    check(await draft.inputValue()==='Unsaved reply محفوظ 🪴','reply draft value retained');
    check(await draft.getAttribute('data-harden-draft')===marker,'same draft DOM retained');return{value:await draft.inputValue(),...await state()};
  });
  await record('real-timeout-without-abort-controller-and-stale-completion',async()=>{
    const storage=await page.context().storageState();
    const context=await page.context().browser().newContext({viewport:{width:1440,height:900},storageState:storage});
    await context.addInitScript(()=>{window.AbortController=undefined;});
    const p=await context.newPage();p.setDefaultTimeout(4000);let held;
    try{
      await p.goto(base+'/inbox?scope=starred&order=active');await p.route('**/inbox/preview/*',r=>{held=r;});
      const link=p.locator('[data-inbox-preview-url]').first(),id=await link.evaluate(n=>n.closest('[data-inbox-row]').dataset.threadId);
      const started=Date.now();await link.click();
      await p.waitForFunction(()=>document.querySelector('[data-inbox-status]').textContent.includes('Loading topic'));
      check(await p.locator('[data-inbox-reading]').getAttribute('aria-busy')==='true','timeout loading state visible');
      await p.locator('[data-inbox-recovery]').waitFor({state:'visible',timeout:18000});const elapsed=Date.now()-started;
      const timed=await state(p);check(elapsed>=14500&&elapsed<18000,'real 15s timeout',elapsed);check(timed.busy===null&&timed.status.includes('taking longer'),'timeout offers recovery',timed);
      await held.fulfill({status:200,contentType:'text/html',body:'<article data-inbox-preview="'+id+'"><h2>Late stale topic</h2></article>'});
      await p.waitForTimeout(150);check(await p.locator('[data-inbox-preview]').count()===0,'late response ignored without AbortController');
      check((await state(p)).status===timed.status,'late response preserves timeout recovery');
      await p.unroute('**/inbox/preview/*');await p.getByRole('button',{name:'Try again',exact:true}).click();await p.locator('[data-inbox-preview="'+id+'"]').waitFor();
      check((await state(p)).busy===null&&!await p.locator('[data-inbox-recovery]').isVisible(),'timeout retry succeeds');
      return{elapsed,noAbortController:await p.evaluate(()=>typeof AbortController==='undefined'),timed};
    }finally{await context.close();}
  });
  await record('latest-request-wins',async()=>{
    await page.setViewportSize({width:1440,height:900});await page.goto(base+'/inbox?scope=starred&order=active');
    const links=page.locator('[data-inbox-preview-url]'),ids=await links.evaluateAll(ns=>ns.slice(0,2).map(n=>n.closest('[data-inbox-row]').dataset.threadId));
    await page.route('**/inbox/preview/*',async r=>{const id=new URL(r.request().url()).pathname.split('/').pop(),response=await r.fetch();if(id===ids[0])await new Promise(resolve=>setTimeout(resolve,250));await r.fulfill({response});});
    await links.nth(0).click();await links.nth(1).click();await page.waitForTimeout(450);
    const current=await page.locator('[data-inbox-preview]').getAttribute('data-inbox-preview');check(current===ids[1],'latest preview wins', {ids,current});
    check(await page.locator('[data-inbox-reading]').getAttribute('aria-busy')===null,'latest request clears busy');return{ids,current};
  });
  await record('bulk-422-retains-selection-and-canonical-history',async()=>{
    await page.goto(base+'/inbox?scope=starred&order=commended');
    const selected=await page.locator('[data-inbox-select]').evaluateAll(ns=>ns.slice(0,2).map(n=>n.value));
    await page.locator('[data-inbox-select]').nth(0).check();await page.locator('[data-inbox-select]').nth(1).check();
    await page.locator('[data-inbox-sweep] input[name="until"]').evaluate(n=>n.value='retired-date');
    await page.locator('[data-inbox-bulk-menu] > summary').click();
    const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/inbox/bulk'&&r.request().method()==='POST');
    await page.locator('[data-inbox-bulk-menu] button[value="snooze"]').click();const status=(await response).status();
    await page.locator('[role="alert"]').filter({hasText:'available selections'}).waitFor();
    const retained=await page.locator('[data-inbox-select]:checked').evaluateAll(ns=>ns.map(n=>n.value));
    check(status===422,'native bulk validation 422',status);check(JSON.stringify(retained.sort())===JSON.stringify(selected.sort()),'bulk valid selections retained',{selected,retained});
    check(new URL(page.url()).pathname==='/inbox'&&new URL(page.url()).searchParams.get('order')==='commended','422 enhanced history canonical',page.url());
    await page.locator('[data-inbox-preview-url]').first().click();await page.locator('[data-inbox-preview]').waitFor();
    check(new URL(page.url()).pathname==='/inbox'&&new URL(page.url()).searchParams.has('t'),'preview after422 stays Inbox history',page.url());
    await page.screenshot({path:out+'/'+engine+'-final-bulk-validation.png'});return{status,selected,retained,...await state()};
  });
  await record('explicit-repeated-bulk-star',async()=>{
    const rounds=[];
    for(let i=0;i<2;i++){
      await page.goto(base+'/inbox?scope=starred&order=active');await page.locator('[data-inbox-select]').first().check();
      const id=await page.locator('[data-inbox-select]').first().getAttribute('value');await page.locator('[data-inbox-bulk-menu] > summary').click();
      const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/inbox/bulk'&&r.request().method()==='POST');
      await page.locator('[data-inbox-bulk-menu] button[value="star"]').click();const status=(await response).status();
      await page.waitForURL(/\/inbox\?scope=starred/);const pressed=await page.locator('[data-inbox-row][data-thread-id="'+id+'"] .star-toggle').getAttribute('aria-pressed');
      check(status===303&&pressed==='true','explicit bulk Star remains on round'+i,{status,pressed});rounds.push({id,status,pressed});
    }return{rounds};
  });
  await record('native-no-js-validation-recovery',async()=>{
    const context=await page.context().browser().newContext({viewport:{width:393,height:844},javaScriptEnabled:false,storageState:await page.context().storageState()});
    const p=await context.newPage();p.setDefaultTimeout(4000);
    try{
      await p.goto(base+'/inbox?scope=starred&order=active');await p.locator('[data-inbox-select]').first().check();
      await p.locator('[data-inbox-sweep] > button[value="read"]').evaluate(n=>n.value='invalid');
      const response=p.waitForResponse(r=>new URL(r.url()).pathname==='/inbox/bulk'&&r.request().method()==='POST');
      await p.locator('[data-inbox-sweep] > button').first().click();const status=(await response).status();
      await p.locator('[role="alert"]').filter({hasText:'available selections'}).waitFor();
      check(status===422&&await p.locator('[data-inbox-select]:checked').count()===1,'noJS validation keeps checked selection',{status});
      check(await p.locator('[data-inbox-sweep]').isVisible(),'native bulk recovery remains usable');return{status,...await state(p)};
    }finally{await context.close();}
  });
  await record('short-creation-and-unicode',async()=>{
    await page.setViewportSize({width:320,height:180});await page.goto(base+'/inbox?scope=starred&order=active');
    await page.evaluate(()=>document.documentElement.style.fontSize='200%');await page.locator('.create-menu > summary').evaluate(n=>n.click());
    await page.waitForFunction(()=>document.querySelector('.create-menu').hasAttribute('data-inbox-menu-positioned'));
    const panel=await page.locator('.create-menu-panel').evaluate(n=>({rect:n.getBoundingClientRect().toJSON(),maxHeight:getComputedStyle(n).maxHeight,overflow:getComputedStyle(n).overflowY,scroll:n.scrollHeight,client:n.clientHeight}));
    check(panel.rect.y>=0&&panel.rect.bottom<=180.5&&panel.overflow==='auto','short creation panel contained and scrolls',panel);
    await page.screenshot({path:out+'/'+engine+'-final-short-creation.png'});
    await page.setViewportSize({width:320,height:650});await page.goto(base+'/inbox?scope=starred&order=active');
    const row=page.locator('[data-inbox-row][data-thread-id="3"]');await row.scrollIntoViewIfNeeded();
    const unicode=await row.locator('.thread-title').evaluate(n=>({title:n.getBoundingClientRect().toJSON(),row:n.closest('[data-inbox-row]').getBoundingClientRect().toJSON(),text:n.textContent}));
    check(unicode.title.x>=unicode.row.x&&unicode.title.right<=unicode.row.right,'mixed script title contained',unicode);
    const s=await state();check(s.scrollWidth<=s.documentWidth,'English shell with mixed content no page overflow',s);
    await page.screenshot({path:out+'/'+engine+'-final-unicode.png'});return{panel,unicode,state:s};
  });
  await record('forced-colors-visible-switch-and-immediate-menu',async()=>{
    await page.setViewportSize({width:393,height:844});await page.emulateMedia({forcedColors:'active'});await page.goto(base+'/inbox?scope=starred&order=active');
    await page.locator('[data-inbox-row-menu]').first().locator(':scope > summary').click();await page.waitForFunction(()=>document.querySelector('[data-inbox-row-menu][open]').hasAttribute('data-inbox-menu-positioned'));
    const result=await page.locator('[data-inbox-row-menu][open]').evaluate(n=>{const panel=getComputedStyle(n.querySelector('.thread-row-menu-panel')),track=getComputedStyle(n.querySelector('.inbox-switch-track')),thumb=getComputedStyle(n.querySelector('.inbox-switch-thumb'));return{forced:matchMedia('(forced-colors:active)').matches,panel:{animation:panel.animationName,opacity:panel.opacity},track:{background:track.backgroundColor},thumb:{background:thumb.backgroundColor,border:thumb.borderColor,borderWidth:thumb.borderWidth},checked:n.querySelector('[role="switch"]').getAttribute('aria-checked')};});
    check(result.panel.animation==='none'&&result.panel.opacity==='1','forced-colors immediate opaque panel',result);
    check(result.thumb.background!==result.track.background&&parseFloat(result.thumb.borderWidth)>=1,'forced-colors thumb visible',result);
    await page.screenshot({path:out+'/'+engine+'-final-forced-colors.png'});await page.emulateMedia({forcedColors:'none'});return result;
  });
  await record('native-submit-cancels-inflight-preview',async()=>{
    await page.setViewportSize({width:1440,height:900});await page.goto(base+'/inbox?scope=starred&order=active');let held;
    await page.route('**/inbox/preview/*',r=>{held=r;});await page.locator('[data-inbox-preview-url]').first().focus();
    const id=await page.locator('[data-inbox-row]').first().getAttribute('data-thread-id');
    await page.keyboard.press('j');await page.waitForFunction(()=>document.querySelector('[data-inbox-reading]').getAttribute('aria-busy')==='true');
    const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/t/'+id+'/snooze'&&r.request().method()==='POST');
    await page.keyboard.press('#');const status=(await response).status();await page.waitForURL(/\/inbox\?scope=starred/);await page.waitForTimeout(150);
    check(status===303&&new URL(page.url()).pathname==='/inbox','native submission owns navigation',{status,url:page.url()});
    check(await page.locator('[data-inbox-row][data-thread-id="'+id+'"]').count()===0,'shortcut hiding persisted');
    await page.goto(base+'/inbox?scope=snoozed');check(await page.locator('[data-inbox-row][data-thread-id="'+id+'"]').count()===1,'hidden topic recoverable in Snoozed');return{id,status};
  });
  check(errors.length===0,'no page script errors',errors);
  return{engine,build,assets,checks,failures,cases,errors,limits:['Linux Chromium/WebKit; no physical iPhone/Safari','Forced-colors palette meaningful in Chromium only; WebKit emulates query','200% root text scaling not physical browser zoom','Whole shell RTL unsupported; mixed-script content verified in existing English shell']};
}
