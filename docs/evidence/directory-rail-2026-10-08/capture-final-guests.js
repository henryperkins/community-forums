async page => {
 const base='http://127.0.0.1:8484',out='/home/ubuntu/community-forums/docs/evidence/directory-rail-2026-10-08',engine=page.context().browser().browserType().name(),checks=[],cases=[],context=await page.context().browser().newContext({viewport:{width:1440,height:900}}),p=await context.newPage();
 p.setDefaultTimeout(5000);p.setDefaultNavigationTimeout(6000);const check=(name,pass,detail)=>checks.push({name,pass:!!pass,detail});
 for(const width of [1440,393])for(const pane of ['boards','tags','connections']){
  await p.setViewportSize({width,height:width===1440?900:844});await p.goto(base+'/?pane='+pane);const m=await p.evaluate(()=>({h1:[...document.querySelectorAll('main h1')].map(n=>n.textContent.trim()),subheader:!!document.querySelector('[data-subheader]'),oldTabs:!!document.querySelector('.forum-directory__tabs'),links:[...document.querySelectorAll('[data-directory-link]')].map(n=>({key:n.dataset.directoryLink,current:n.getAttribute('aria-current')})),client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}));const name='guest '+width+' '+pane;
  check(name+' single content heading and no empty creation row',m.h1.length===1&&!m.subheader&&!m.oldTabs,m);
  check(name+' global current and no overflow',m.links.map(n=>n.key).join(',')==='boards,tags,connections'&&m.links.filter(n=>n.current==='page').map(n=>n.key).join(',')===pane&&m.scroll<=m.client+1,m);
  if(width===393){await p.getByRole('button',{name:'Open board rail',exact:true}).click();await p.waitForFunction(()=>Math.abs(document.querySelector('[data-sidebar]').getBoundingClientRect().x)<1);check(name+' drawer focus/inert',await p.locator('[data-nav-close]').evaluate(n=>n===document.activeElement)&&await p.locator('main').evaluate(n=>n.inert));await p.keyboard.press('Tab');check(name+' drawer first stop Boards',await p.locator('[data-directory-link="boards"]').evaluate(n=>n===document.activeElement));await p.keyboard.press('Escape');check(name+' drawer restores opener',await p.getByRole('button',{name:'Open board rail',exact:true}).evaluate(n=>n===document.activeElement));}
  if(pane==='connections')await p.screenshot({path:out+'/'+engine+'-final-'+width+'-guest-connections.png'});cases.push({name,metrics:m});
 }
 await context.close();return{engine,checks,cases};
}
