async page => {
 const base='http://127.0.0.1:8484',out='/home/ubuntu/community-forums/docs/evidence/directory-rail-2026-10-08',engine=page.context().browser().browserType().name();
 page.setDefaultTimeout(5000);page.setDefaultNavigationTimeout(6000);
 const checks=[],cases=[],errors=[];const check=(name,pass,detail)=>checks.push({name,pass:!!pass,detail});page.on('pageerror',e=>errors.push(e.message));
 const metrics=async p=>p.evaluate(()=>{
  const rect=n=>n?n.getBoundingClientRect().toJSON():null;
  const heading=document.querySelector('main h1'),context=document.querySelector('[data-directory-context]'),identity=document.querySelector('.directory-identity'),trigger=document.querySelector('[data-create-trigger]');
  const rgb=s=>{const m=s.match(/[\d.]+/g);return m?m.map(Number):[0,0,0,0]};
  const contrast=n=>{if(!n)return null;const fg=rgb(getComputedStyle(n).color);let parent=n,bg;while(parent){bg=rgb(getComputedStyle(parent).backgroundColor);if(bg.length===3||bg[3]>=1)break;parent=parent.parentElement;}const lum=c=>c.slice(0,3).map(v=>v/255).map(v=>v<=.04045?v/12.92:Math.pow((v+.055)/1.055,2.4)).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0);const a=lum(fg),b=lum(bg||[255,255,255]);return{foreground:fg,background:bg,ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};};
  return{url:location.pathname+location.search,client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,theme:document.documentElement.dataset.theme,fontSize:getComputedStyle(document.documentElement).fontSize,subheader:rect(document.querySelector('[data-subheader]')),leading:rect(document.querySelector('.forum-subheader-leading')),heading:rect(heading),headingText:heading?.textContent.trim(),h1Count:document.querySelectorAll('main h1').length,context:rect(context),identity:rect(identity),identityLink:document.querySelector('.directory-identity a')?.getAttribute('href'),identityContrast:contrast(identity),creation:rect(trigger),oldTabs:document.querySelectorAll('nav[aria-label="Board index panes"],.forum-directory__tabs').length,links:[...document.querySelectorAll('[data-directory-link]')].map(n=>({key:n.dataset.directoryLink,href:n.getAttribute('href'),text:n.textContent.trim(),current:n.getAttribute('aria-current'),tag:n.tagName,rect:rect(n)})),groups:[...document.querySelectorAll('[data-sidebar] .board-rail-cat')].map(n=>n.textContent.trim()),group:rect(document.querySelector('[data-directory-nav]')),rail:rect(document.querySelector('[data-sidebar]')),mainInert:document.querySelector('main')?.inert,primary:[...document.querySelectorAll('[data-primary-route]')].map(n=>({key:n.dataset.primaryRoute,current:n.getAttribute('aria-current')})),bellCurrent:document.querySelector('[data-bell]')?.getAttribute('aria-current'),badges:[...document.querySelectorAll('[data-notification-count]')].map(n=>({text:n.textContent.trim(),hidden:n.hidden})),focused:document.activeElement?.getAttribute('aria-label')||document.activeElement?.dataset.directoryLink||document.activeElement?.tagName};
 });
 const settled=async()=>{await page.getByRole('button',{name:'Open board rail',exact:true}).click();await page.waitForFunction(()=>Math.abs(document.querySelector('[data-sidebar]').getBoundingClientRect().x)<1);};
 await page.goto(base+'/?pane=connections');
 const assets=await page.evaluate(async()=>Promise.all([...document.querySelectorAll('link[rel="stylesheet"],script[src]')].map(n=>n.href||n.src).filter(u=>/app-style-|app-[0-9a-f]+\.js/.test(u)).map(async u=>{const r=await fetch(u),b=await r.arrayBuffer();return{url:new URL(u).pathname,status:r.status,sha256:[...new Uint8Array(await crypto.subtle.digest('SHA-256',b))].map(v=>v.toString(16).padStart(2,'0')).join('')};})));
 check('final asset hashes',assets.some(a=>a.sha256==='64a0335378e14855eb095d02ffe5e97cfdef2aa605e67c8db0e3a82e4e4e8eb2')&&assets.some(a=>a.sha256==='3bc29160425933ecf16bfc4ca8c791e0af8ad153dc96875bad9858026a078e88'),assets);
 for(const width of [1440,900,393,320])for(const theme of ['light','dark'])for(const pane of ['boards','tags','connections']){
  await page.setViewportSize({width,height:width>=900?900:844});await page.goto(base+'/?pane='+pane);await page.evaluate(t=>{document.documentElement.dataset.theme=t;return document.fonts.ready;},theme);
  const m=await metrics(page),name=width+' '+theme+' '+pane;
  check(name+' global group order/current',m.links.map(n=>n.key).join(',')==='boards,tags,connections'&&m.links.filter(n=>n.current==='page').map(n=>n.key).join(',')===pane,m.links);
  check(name+' native links / 44px rows',m.links.every(n=>n.tag==='A'&&n.href==='/?pane='+n.key&&n.rect.height>=43.9),m.links.map(n=>({key:n.key,height:n.rect.height})));
  check(name+' hierarchy / removed strip',m.groups[0]==='Explore'&&m.groups[1]==='Reading list'&&m.oldTabs===0,m.groups);
  check(name+' one h1 / no overflow',m.h1Count===1&&m.scroll<=m.client+1,{h1:m.headingText,scroll:m.scroll,client:m.client});
  const title=pane==='boards'?m.context:m.heading;
  check(name+' heading/creation containment',!!title&&m.creation.width>=43.9&&title.right<=m.creation.x+1&&title.y>=m.subheader.y-1&&title.bottom<=m.subheader.bottom+1,{title,creation:m.creation,subheader:m.subheader});
  if(pane==='connections')check(name+' linked identity contrast',m.identityLink==='/u/alice'&&m.identityContrast.ratio>=4.5,m.identityContrast);
  if(pane==='connections'&&[1440,393].includes(width))await page.screenshot({path:out+'/'+engine+'-final-'+width+'-'+theme+'-connections.png'});
  if(width<900&&theme==='light'&&pane==='connections'){
   await settled();const drawer=await metrics(page);await page.screenshot({path:out+'/'+engine+'-final-'+width+'-light-connections-drawer.png'});
   check(name+' drawer focus/inert/containment',drawer.focused==='Close board rail'&&drawer.mainInert&&drawer.rail.x>=-1&&drawer.rail.right<=width+1,{focused:drawer.focused,mainInert:drawer.mainInert,rail:drawer.rail});
   await page.keyboard.press('Tab');check(name+' first stop Boards',await page.locator('[data-directory-link="boards"]').evaluate(n=>n===document.activeElement));
   await page.keyboard.press('Escape');check(name+' Escape restores opener',await page.getByRole('button',{name:'Open board rail',exact:true}).evaluate(n=>n===document.activeElement)&&!(await page.locator('main').evaluate(n=>n.inert)));
   await settled();const scrimPoint=await page.evaluate(()=>({x:(document.querySelector('[data-sidebar]').getBoundingClientRect().right+document.querySelector('[data-nav-scrim]').getBoundingClientRect().right)/2,y:200}));await page.mouse.click(scrimPoint.x,scrimPoint.y);check(name+' scrim restores opener',await page.getByRole('button',{name:'Open board rail',exact:true}).evaluate(n=>n===document.activeElement));
  }
  cases.push({name,metrics:m});
 }
 await page.setViewportSize({width:1440,height:900});
 for(const start of ['/inbox','/c/general','/compose?board=general','/u/bob?tab=connections']){
  await page.goto(base+start);const before=await metrics(page);check(start+' has neutral global group',before.links.length===3&&before.links.every(n=>n.current===null),before.links);
  await Promise.all([page.waitForURL(base+'/?pane=connections'),page.locator('[data-directory-link="connections"]').click()]);const after=await metrics(page);check(start+' Explore navigates to own Connections',after.identityLink==='/u/alice'&&after.links.find(n=>n.key==='connections')?.current==='page',after.url);
 }
 for(const path of ['/tags','/tags/rail-review']){
  const response=await page.goto(base+path);const m=await metrics(page);check(path+' canonical tag current',response.status()===200&&m.links.filter(n=>n.current==='page').map(n=>n.key).join(',')==='tags',{status:response.status(),links:m.links});
 }
 await page.goto(base+'/?pane=connections&connection=following');check('Following list remains current',await page.locator('nav[aria-label="Connection lists"] a[aria-current="page"]').getAttribute('href')==='/?pane=connections&connection=following');
 for(const path of ['/?pane=notices','/notifications']){
  await page.goto(base+path);const m=await metrics(page);check(path+' bell current / no directory or primary Boards current',m.bellCurrent==='page'&&m.links.every(n=>n.current===null)&&m.primary.find(n=>n.key==='boards')?.current===null,m);check(path+' three notification counts retained',m.badges.length===3&&m.badges.every(n=>n.text===m.badges[0].text)&&m.badges[0].text==='1',m.badges);
 }
 await page.setViewportSize({width:393,height:844});await page.goto(base+'/inbox');await settled();await Promise.all([page.waitForURL(base+'/?pane=tags'),page.locator('[data-directory-link="tags"]').click()]);
 check('phone native directory route dismisses drawer',!(await page.locator('body').evaluate(n=>n.classList.contains('nav-open')))&&!(await page.locator('main').evaluate(n=>n.inert)));
 await page.goto(base+'/?pane=connections');await page.locator('[data-create-trigger]').click();await page.waitForFunction(()=>document.querySelector('[data-create-menu]').open);const menu=await page.locator('.create-menu-panel').evaluate(n=>({rect:n.getBoundingClientRect().toJSON(),links:[...n.querySelectorAll('a')].map(a=>({text:a.textContent.trim(),href:a.getAttribute('href')}))}));check('Create menu remains contained and native',menu.rect.x>=0&&menu.rect.right<=393&&menu.rect.bottom<=844&&menu.links.length===2,menu);
 await page.keyboard.press('Escape');
 await page.goto(base+'/compose?board=general');const title=page.locator('[data-compose-title]');await title.fill('Kept directory confirmation draft');await page.locator('[data-composer-body]').count().then(async count=>{if(count)await page.locator('[data-composer-body]').fill('The existing compose draft should be retained.');});
 await settled();await page.locator('[data-compose-board-picker="announcements"]').click();check('enhanced compose destination remains picker and draft preserved',await page.locator('[data-compose-board-select] option:checked').getAttribute('data-board-slug')==='announcements'&&await title.inputValue()==='Kept directory confirmation draft'&&new URL(page.url()).pathname==='/compose');
 await page.keyboard.press('Escape');
 const browser=page.context().browser(),state=await page.context().storageState(),noJS=[];
 for(const member of [false,true]){
  const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:393,height:844},storageState:member?state:undefined});const p=await context.newPage();
  for(const pane of ['boards','tags','connections']){
   await p.goto(base+'/?pane='+pane);const m=await metrics(p);check('noJS '+(member?'member':'guest')+' '+pane+' native/current and heading',m.links.length===3&&m.links.find(n=>n.key===pane)?.current==='page'&&m.h1Count===1&&m.oldTabs===0&&m.scroll<=m.client+1,m);check('noJS '+(member?'member':'guest')+' '+pane+' rail bounded / guest no empty subheader',m.rail.height<=176.1&&(member||m.subheader===null),{rail:m.rail,subheader:m.subheader});
   if(pane==='connections')await p.screenshot({path:out+'/'+engine+'-final-393-nojs-'+(member?'member':'guest')+'-connections.png'});noJS.push({member,pane,metrics:m});
  }
  await Promise.all([p.waitForURL(base+'/?pane=tags'),p.locator('[data-directory-link="tags"]').click()]);check('noJS '+(member?'member':'guest')+' Explore native navigation',new URL(p.url()).search==='?pane=tags');
  if(member){await p.goto(base+'/compose?board=general');await Promise.all([p.waitForURL(base+'/compose?board=announcements'),p.locator('[data-compose-board-picker="announcements"]').click()]);check('noJS compose destination native GET selection',await p.locator('[data-compose-board-select] option:checked').getAttribute('data-board-slug')==='announcements');}
  await context.close();
 }
 check('no page errors',errors.length===0,errors);
 return{engine,build:'350617f1bb175373',assets,checks,cases,noJS,errors,limits:['Linux headless Chromium/WebKit, no physical iPhone/Safari or Firefox','Large text and independent gate checks follow in the same bounded correction batch','No detector score used; contrast derived from computed text color and first opaque ancestor background']};
}
