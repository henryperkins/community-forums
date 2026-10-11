async page => {
  const engine = page.context().browser().browserType().name();
  const checks=[];
  const cases=[];
  const failures=[];
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  const check=(pass,label,detail)=>{checks.push(label);if(!pass)failures.push({label,detail});};
  const matrix=engine==='webkit' ? [[1440,'os'],[1440,'app'],[393,'os'],[393,'app']] : [[1440,'normal']];
  for(const [width,mode] of matrix) {
    await page.setViewportSize({width,height:900});
    await page.emulateMedia({reducedMotion:mode==='os' ? 'reduce' : 'no-preference'});
    await page.goto('http://127.0.0.1:8483/inbox');
    await page.evaluate(mode=>{document.documentElement.setAttribute('data-theme','light');document.documentElement.setAttribute('data-reduced-motion',mode==='app' ? '1' : '0');},mode);
    const menus=width===1440 ? ['.inbox-scope-menu','.inbox-sort-menu','.inbox-actions','.create-menu','[data-inbox-row-menu]'] : ['.inbox-compact-menu','.inbox-actions','.create-menu','[data-inbox-row-menu]'];
    const results=[];
    for(const selector of menus) {
      const frames=await page.evaluate(async selector=>{const menu=document.querySelector(selector),node=menu.querySelector(':scope > :is(.inbox-menu-panel,.thread-row-menu-panel,.create-menu-panel)');menu.querySelector(':scope > summary').click();const origin=performance.now(),frames=[];while(performance.now()-origin<180){await new Promise(requestAnimationFrame);const style=getComputedStyle(node),rect=node.getBoundingClientRect();frames.push({at:performance.now()-origin,visibility:style.visibility,positioned:menu.getAttribute('data-inbox-menu-positioned'),rect:rect.toJSON(),opacity:style.opacity,animation:style.animationName,transform:style.transform,transitionProperty:style.transitionProperty,animations:node.getAnimations().filter(a=>a.playState!=='finished').map(a=>({name:a.animationName||a.transitionProperty,playState:a.playState}))});}menu.querySelector(':scope > summary').click();return frames;},selector);
      const visible=frames.filter(f=>f.visibility==='visible' && f.positioned==='1');
      const last=visible[visible.length-1];
      check(visible.length>0,`${width}/${mode}/${selector}/paints`);
      check(visible.every(f=>f.rect.x>=0 && f.rect.right<=width+1 && f.rect.y>=0 && f.rect.bottom<=901),`${width}/${mode}/${selector}/every painted frame contained`,visible[0]);
      check(visible.every(f=>Math.abs(f.rect.x-last.rect.x)<.5 && Math.abs(f.rect.y-last.rect.y)<.5 && f.transform==='none' && f.transitionProperty==='none'),`${width}/${mode}/${selector}/all geometry instant`,visible[0]);
      if(mode!=='normal') check(visible.every(f=>f.opacity==='1' && f.animation==='none' && f.animations.length===0),`${width}/${mode}/${selector}/reduced instant`,visible[0]);
      results.push({selector,frames});
    }
    const keyMenu=page.locator(menus[0]);
    await page.locator('.inbox-actions > summary').click();
    await page.getByRole('button',{name:'Help',exact:true}).click();
    await page.evaluate(()=>{window.motionFocusEvents=[];document.addEventListener('focusin',event=>window.motionFocusEvents.push({tag:event.target.tagName,label:event.target.getAttribute('aria-label'),text:(event.target.textContent||'').trim().slice(0,50)}));});
    await page.keyboard.press('Escape');
    await keyMenu.locator('summary').first().focus();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await page.evaluate(async()=>{await new Promise(requestAnimationFrame);await new Promise(requestAnimationFrame);});
    const focusEvents=await page.evaluate(()=>window.motionFocusEvents);
    const keyState=await keyMenu.locator('.inbox-menu-panel').evaluate(node=>({within:node.contains(document.activeElement),opacity:getComputedStyle(node).opacity,animation:getComputedStyle(node).animationName}));
    check(keyState.within && keyState.opacity==='1' && keyState.animation==='none',`${width}/${mode}/actual Enter Tab full contrast`,keyState);
    await page.keyboard.press('Escape');
    check(await keyMenu.locator('summary').first().evaluate(node=>node===document.activeElement),`${width}/${mode}/actual Escape return`);
    await page.locator('.inbox-actions > summary').click();
    await page.getByRole('button',{name:'Help',exact:true}).click();
    await page.getByRole('button',{name:'Close Inbox help',exact:true}).click();
    await page.waitForFunction(()=>document.activeElement===document.querySelector('.inbox-actions > summary'));
    const helpReturn=await page.locator('.inbox-actions > summary').evaluate(node=>node===document.activeElement);
    check(helpReturn,`${width}/${mode}/ordinary Help close returns focus`);
    cases.push({width,mode,results,keyState,focusEvents,helpReturn});
  }
  const assets=await page.evaluate(async()=>Promise.all([...document.querySelectorAll('link[rel="stylesheet"],script[src]')].map(n=>n.href||n.src).filter(url=>/app-style-|app-[0-9a-f]+\.js/.test(url)).map(async url=>{const response=await fetch(url),body=await response.arrayBuffer();return {url:new URL(url).pathname,status:response.status,sha256:[...new Uint8Array(await crypto.subtle.digest('SHA-256',body))].map(n=>n.toString(16).padStart(2,'0')).join('')};})));
  return {engine,checks:checks.length,failures,cases,assets,errors};
}
