async page => {
 const out='/home/ubuntu/community-forums/docs/evidence/directory-rail-2026-10-08',cases=[];
 for(const width of [393,320]){
  await page.setViewportSize({width,height:844});await page.goto('http://127.0.0.1:8484/?pane=connections');
  await page.getByRole('button',{name:'Open board rail',exact:true}).click();
  await page.waitForFunction(()=>{const r=document.querySelector('[data-sidebar]').getBoundingClientRect();return Math.abs(r.x)<1&&r.right>200;});
  const drawer=await page.evaluate(()=>({rect:document.querySelector('[data-sidebar]').getBoundingClientRect().toJSON(),focused:document.activeElement.getAttribute('aria-label'),links:[...document.querySelectorAll('[data-sidebar] a[href],[data-sidebar] button')].filter(n=>n.getClientRects().length).map(n=>({name:n.getAttribute('aria-label')||n.textContent.trim(),href:n.getAttribute('href'),height:n.getBoundingClientRect().height})),mainInert:document.querySelector('main').inert}));
  await page.screenshot({path:out+'/chromium-initial-'+width+'-connections-drawer-settled.png'});await page.keyboard.press('Escape');
  cases.push({width,drawer,focusRestored:await page.getByRole('button',{name:'Open board rail',exact:true}).evaluate(n=>n===document.activeElement)});
 }
 return{engine:'chromium',build:'e26e139ee9899a8f',cases,note:'Initial drawer screenshot helper repaired only to wait until the existing entrance transition reaches x0; no application changes.'};
}
