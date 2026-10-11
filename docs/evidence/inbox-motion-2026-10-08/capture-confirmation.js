async page => {
  const base = 'http://127.0.0.1:8483';
  const engine = page.context().browser().browserType().name();
  const out = '/home/ubuntu/community-forums/docs/evidence/inbox-motion-2026-10-08';
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const check = (condition, label, detail = '') => {
    if (!condition) failures.push({label, detail});
    checks++;
  };
  let checks = 0;
  const failures = [];
  const cases = [];
  const snapshot = await page.locator('main').ariaSnapshot();
  const assets = await page.evaluate(async () => {
    const urls = [...document.querySelectorAll('link[rel="stylesheet"],script[src]')].map(node => node.href || node.src).filter(url => /app-style-|app-[0-9a-f]+\.js/.test(url));
    return Promise.all(urls.map(async url => {
      const response = await fetch(url);
      const buffer = await response.arrayBuffer();
      const hash = [...new Uint8Array(await crypto.subtle.digest('SHA-256', buffer))].map(n => n.toString(16).padStart(2,'0')).join('');
      return {url: new URL(url).pathname,status: response.status,sha256: hash};
    }));
  });
  const sample = async (selector, activate) => page.evaluate(async ({selector, activate}) => {
    const node = document.querySelector(selector);
    if (activate === 'menu') node.parentElement.querySelector(':scope > summary').click();
    if (activate === 'selection') document.querySelector('[data-inbox-select]').click();
    if (activate === 'help') document.querySelector('[data-inbox-help-open]').click();
    const origin = performance.now();
    const frames = [];
    while (performance.now() - origin < 330) {
      await new Promise(requestAnimationFrame);
      const style = getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      const caret = node.parentElement.querySelector(':scope > summary > .icon');
      frames.push({at: +(performance.now()-origin).toFixed(2),opacity: +style.opacity,animationName:style.animationName,duration:style.animationDuration,transform:style.transform,visibility:style.visibility,positioned:node.parentElement.getAttribute('data-inbox-menu-positioned'),
        rect:{x:rect.x,y:rect.y,width:rect.width,height:rect.height},caret:caret ? getComputedStyle(caret).transform : null,
        animations: node.getAnimations().filter(animation=>animation.playState!=='finished').map(animation => ({name:animation.animationName || animation.transitionProperty,currentTime:animation.currentTime,playState:animation.playState,keyframes:animation.effect.getKeyframes().map(frame => ({offset:frame.offset,opacity:frame.opacity,transform:frame.transform}))}))});
    }
    return {frames,focused:document.activeElement && document.activeElement.tagName,positioned:node.parentElement.getAttribute('data-inbox-menu-positioned')};
  }, {selector,activate});
  for (const width of [1440,393]) {
    await page.setViewportSize({width,height:900});
    for (const theme of ['light','dark']) {
      for (const motion of ['normal','os-reduced','app-reduced']) {
        await page.emulateMedia({reducedMotion:motion === 'os-reduced' ? 'reduce' : 'no-preference'});
        await page.goto(base + '/inbox');
        await page.evaluate(({theme,motion}) => {
          document.documentElement.setAttribute('data-theme',theme);
          document.documentElement.setAttribute('data-reduced-motion',motion === 'app-reduced' ? '1' : '0');
        },{theme,motion});
        await page.mouse.click(2,2);
        const context = {width,theme,motion,menus:[]};
        const menuSelectors = width === 1440 ? ['.inbox-scope-menu','.inbox-sort-menu','.inbox-actions','.create-menu'] : ['.inbox-compact-menu','.inbox-actions','.create-menu'];
        menuSelectors.push('[data-inbox-row-menu]');
        for (const menuSelector of menuSelectors) {
          const menu = page.locator(menuSelector).first();
          const selector = menuSelector + ' > :is(.inbox-menu-panel,.thread-row-menu-panel,.create-menu-panel)';
          const timeline = await sample(selector,'menu');
          const painted = timeline.frames.filter(frame => frame.rect.width > 0 && frame.visibility === 'visible' && frame.positioned === '1');
          const first = painted[0];
          const last = painted[painted.length-1];
          const label = `${engine}/${width}/${theme}/${motion}/${menuSelector}`;
          check(!!first,label+' paints');
          if (first) {
            check(painted.every(frame=>frame.transform==='none'),label+' geometry has no transform');
            check(painted.every(frame=>Math.abs(frame.rect.x-last.rect.x)<.5 && Math.abs(frame.rect.y-last.rect.y)<.5 && Math.abs(frame.rect.width-last.rect.width)<.5 && Math.abs(frame.rect.height-last.rect.height)<.5),label+' fixed geometry');
            check(first.rect.x >= 0 && first.rect.x + first.rect.width <= width + 1 && first.rect.y >= 0 && first.rect.y + first.rect.height <= 901,label+' viewport containment',first.rect);
            check(last.opacity === 1,label+' finishes opaque');
            if (motion === 'normal') check(painted.some(frame=>frame.animations.some(animation=>animation.name==='inbox-action-reveal')),label+' has purposeful reveal',first);
            else check(painted.every(frame=>frame.opacity===1 && frame.animations.length===0 && frame.animationName==='none'),label+' reduced motion instant',first);
            if (/scope|sort|compact/.test(menuSelector)) {
              check(last.caret === 'matrix(-1, 0, 0, -1, 0, 0)',label+' caret ends rotated',last.caret);
              if (motion !== 'normal') check(painted.every(frame=>frame.caret === 'matrix(-1, 0, 0, -1, 0, 0)'),label+' caret instant');
            }
          }
          context.menus.push({menuSelector,...timeline});
          if (motion==='normal' && /scope|compact/.test(menuSelector)) await page.screenshot({path:`${out}/${engine}-${width}-${theme}-view.png`});
          await menu.locator(':scope > summary').click();
          check(await menu.evaluate(node=>!node.open),label+' closes immediately');
        }
        const bulk = await sample('.inbox-sweep','selection');
        const bulkFirst = bulk.frames[0];
        check(await page.locator('[data-inbox-select]').first().isChecked(),`${engine}/${width}/${theme}/${motion}/selection immediate`);
        check(bulk.frames[bulk.frames.length-1].opacity === 1,`${engine}/${width}/${theme}/${motion}/bulk opaque`);
        if (motion==='normal') check(bulk.frames.some(frame=>frame.animations.some(animation=>animation.name==='inbox-action-reveal')),`${engine}/${width}/${theme}/${motion}/bulk reveal`);
        else check(bulk.frames.every(frame=>frame.opacity===1 && frame.animations.length===0),`${engine}/${width}/${theme}/${motion}/bulk instant`,bulkFirst);
        context.bulk = bulk;
        await page.getByRole('button',{name:'Clear selection',exact:true}).click();
        check(!await page.locator('.inbox-sweep').isVisible(),`${engine}/${width}/${theme}/${motion}/bulk clear immediate`);
        await page.locator('.inbox-actions > summary').click();
        const help = await sample('.inbox-help-dialog','help');
        context.help = help;
        check(await page.locator('.inbox-help-dialog').isVisible(),`${engine}/${width}/${theme}/${motion}/Help immediately visible`);
        check(help.frames.every(frame=>frame.transform==='none'),`${engine}/${width}/${theme}/${motion}/Help stable`);
        if (motion==='normal') check(help.frames.some(frame=>frame.animations.some(animation=>animation.name==='inbox-action-reveal')),`${engine}/${width}/${theme}/${motion}/Help reveal`);
        else check(help.frames.every(frame=>frame.opacity===1 && frame.animations.length===0),`${engine}/${width}/${theme}/${motion}/Help instant`);
        await page.keyboard.press('Escape');
        check(!await page.locator('.inbox-help-dialog').isVisible(),`${engine}/${width}/${theme}/${motion}/Help Escape`);
        check(await page.locator('.inbox-actions > summary').evaluate(node=>node===document.activeElement),`${engine}/${width}/${theme}/${motion}/Help focus return`);
        const keyboardMenu = page.locator(menuSelectors[0]).first();
        await keyboardMenu.locator(':scope > summary').focus();
        await page.keyboard.press('Enter');
        await page.keyboard.press('Tab');
        const keyboard = await keyboardMenu.locator('.inbox-menu-panel').evaluate(node=>({opacity:getComputedStyle(node).opacity,animation:getComputedStyle(node).animationName,within:node.contains(document.activeElement)}));
        context.keyboard = keyboard;
        check(keyboard.within && keyboard.opacity==='1' && keyboard.animation==='none',`${engine}/${width}/${theme}/${motion}/keyboard full contrast immediate`,keyboard);
        await page.keyboard.press('Escape');
        check(await keyboardMenu.locator(':scope > summary').evaluate(node=>node===document.activeElement),`${engine}/${width}/${theme}/${motion}/menu Escape focus return`);
        const repeat = await keyboardMenu.evaluate(node=>{const summary=node.querySelector(':scope > summary');const states=[];for(let i=0;i<6;i++){summary.click();states.push(node.open);}return states;});
        check(JSON.stringify(repeat)==='[true,false,true,false,true,false]',`${engine}/${width}/${theme}/${motion}/rapid repeat immediate`,repeat);
        context.repeat = repeat;
        context.document = await page.evaluate(()=>({scrollWidth:document.documentElement.scrollWidth,clientWidth:document.documentElement.clientWidth,fonts:document.fonts.status}));
        check(context.document.scrollWidth <= context.document.clientWidth,`${engine}/${width}/${theme}/${motion}/no page overflow`,context.document);
        cases.push(context);
      }
    }
  }
  await page.setViewportSize({width:1440,height:900});
  await page.emulateMedia({reducedMotion:'no-preference'});
  await page.goto(base+'/inbox');
  await page.evaluate(()=>document.documentElement.setAttribute('data-reduced-motion','0'));
  await page.locator('.create-menu > summary').click();
  await page.setViewportSize({width:393,height:900});
  await page.waitForFunction(()=>{const menu=document.querySelector('.create-menu');const box=menu.querySelector('.create-menu-panel').getBoundingClientRect();return menu.open && menu.getAttribute('data-inbox-menu-positioned')==='1' && box.x>=0 && box.right<=document.documentElement.clientWidth+1;});
  const resized = await page.locator('.create-menu-panel').evaluate(node=>({transform:getComputedStyle(node).transform,rect:node.getBoundingClientRect().toJSON()}));
  check(resized.transform==='none' && resized.rect.x>=0 && resized.rect.right<=394,engine+'/open creation menu resize stable',resized);
  const switched = await page.evaluate(async()=>{const selectors=['.inbox-compact-menu','.inbox-actions','.create-menu','[data-inbox-row-menu]'];const results=[];for(const selector of selectors){const menu=document.querySelector(selector);menu.querySelector(':scope > summary').click();await new Promise(requestAnimationFrame);await new Promise(requestAnimationFrame);results.push({selector,open:menu.open,positioned:menu.getAttribute('data-inbox-menu-positioned'),count:document.querySelectorAll('[data-inbox-menu][open],[data-inbox-row-menu][open],[data-create-menu][open]').length});}return results;});
  check(switched.every(state=>state.open && state.positioned==='1' && state.count===1),engine+'/rapid menu switching exclusive',switched);
  await page.keyboard.press('Escape');
  const storage = await page.context().storageState();
  const nativeContext = await page.context().browser().newContext({viewport:{width:393,height:900},javaScriptEnabled:false,storageState:storage,reducedMotion:'no-preference'});
  const native = await nativeContext.newPage();
  await native.goto(base+'/inbox');
  await native.locator('[data-inbox-row-menu]').first().locator(':scope > summary').click();
  const nativePanel = native.locator('.thread-row-menu-panel').first();
  await nativePanel.scrollIntoViewIfNeeded();
  const nativeState = await nativePanel.evaluate(node=>({position:getComputedStyle(node).position,transform:getComputedStyle(node).transform,animation:getComputedStyle(node).animationName,opacity:getComputedStyle(node).opacity,flowHeight:node.closest('[data-inbox-row]').getBoundingClientRect().height,js:document.documentElement.classList.contains('has-js')}));
  check(!nativeState.js && nativeState.position==='static' && nativeState.transform==='none' && +nativeState.opacity >= .8 && +nativeState.opacity <= 1,engine+'/native menu retains flow',nativeState);
  check(await native.getByRole('switch',{name:'Show in Inbox'}).first().isVisible(),engine+'/native switch available');
  await native.screenshot({path:`${out}/${engine}-393-native-row-menu.png`});
  await nativeContext.close();
  check(errors.length===0,engine+'/no page errors',errors);
  return {engine,checks,failures,assets,cases,resized,switched,nativeState,errors,snapshotFirstLine:snapshot.split('\n')[0],limitations:['Linux browser emulation, no physical iPhone','theme and app reduced-motion attribute set directly for runtime state coverage; settings persistence already verified in earlier task']};
}
