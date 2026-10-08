async page => {
  const base='http://127.0.0.1:8484',engine=page.context().browser().browserType().name(),out='/home/ubuntu/community-forums/docs/evidence/inbox-harden-2026-10-08';
  page.setDefaultTimeout(4000);page.setDefaultNavigationTimeout(5000);
  let checks=0;const failures=[];const check=(ok,label,detail)=>{checks++;if(!ok)failures.push({label,detail});};
  await page.setViewportSize({width:393,height:844});await page.goto(base+'/inbox?scope=unread&order=active&page=2');
  const before=await page.evaluate(()=>({rows:document.querySelectorAll('[data-inbox-row]').length,total:document.querySelector('[data-inbox-current-count]').textContent,url:location.pathname+location.search}));
  check(before.rows===1&&before.total==='21','page-two fixture has one row and unread elsewhere',before);
  await page.locator('[data-inbox-preview-url]').first().click();await page.locator('[data-inbox-preview]').waitFor();
  await page.getByRole('button',{name:'Back to topics',exact:true}).click();await page.locator('[data-inbox-empty-state]').waitFor({state:'visible'});
  const drained=await page.evaluate(()=>({message:document.querySelector('[data-inbox-empty-state]').textContent.trim(),focusEmpty:document.activeElement.hasAttribute('data-inbox-empty-state'),total:document.querySelector('[data-inbox-current-count]').textContent,live:document.querySelector('[data-inbox-status]').textContent}));
  check(drained.message.includes('every topic on this page')&&drained.total==='20'&&drained.focusEmpty,'drained page offers recovery and focused explanation',drained);
  check(!drained.message.includes('caught up'),'page drain does not claim whole Inbox caught up',drained);
  check(await page.getByRole('link',{name:'Load remaining topics',exact:true}).isVisible(),'remaining topics native recovery link');
  await page.screenshot({path:out+'/'+engine+'-final-page-drained.png'});
  await page.getByRole('link',{name:'Load remaining topics',exact:true}).click();await page.locator('[data-inbox-row]').first().waitFor();
  const recovered=await page.evaluate(()=>({rows:document.querySelectorAll('[data-inbox-row]').length,url:location.pathname+location.search,total:document.querySelector('[data-inbox-current-count]').textContent}));
  check(recovered.rows===20&&recovered.total==='20','load remaining recovers other unread topics',recovered);
  return{engine,build:'e26e139ee9899a8f',checks,failures,before,drained,recovered};
}
