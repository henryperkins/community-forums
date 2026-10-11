const { chromium, webkit }=require('/home/ubuntu/community-forums/tests/browser/node_modules/@playwright/test');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const root='/home/ubuntu/community-forums';
const out=path.join(root,'docs/evidence/create-menu-2026-10-07/baseline');fs.mkdirSync(out,{recursive:true});
const sources=[{name:'baseline',cwd:'/tmp/community-forums-create-menu-baseline',db:'retroboards_e2e_create_menu_baseline',url:'http://localhost:8026'}, {name:'change',cwd:root,db:'retroboards_e2e_create_menu',url:'http://localhost:8023'}];
function php(source,code){ return execFileSync('php',['-r',`require 'vendor/autoload.php'; \\App\\Core\\Env::load(getcwd().'/.env'); $c=\\App\\Core\\Config::fromFile(getcwd().'/config/config.php'); if(!preg_match('/^retroboards_e2e_create_menu(?:_baseline)?$/',$c->get('db.database')))throw new RuntimeException('Expected isolated proof database'); $db=new \\App\\Core\\Database($c->get('db')); ${code}`],{cwd:source.cwd,env:{...process.env,DB_DATABASE:source.db}}).toString().trim(); }
(async()=>{
const results=[];
for(const source of sources){
 const id=php(source,`$u=new \\App\\Repository\\UserRepository($db);$ids=[];foreach(['menu_member','menu_other'] as $name){$row=$u->findByUsername($name);$uid=$row['id']??$u->create(['username'=>$name,'email'=>$name.'@retro.test','display_name'=>'Create menu member','password_hash'=>(new \\App\\Security\\PasswordHasher())->hash('password123')]);$db->run('UPDATE users SET onboarded_at=UTC_TIMESTAMP(),email_verified_at=UTC_TIMESTAMP(),post_count=1 WHERE id=?',[$uid]);(new \\App\\Repository\\UserPreferenceRepository($db))->merge((int)$uid,['rail_open'=>true]);$ids[$name]=(int)$uid;} $c=new \\App\\Repository\\ConversationRepository($db);$cid=$c->findOrCreateBetween($ids['menu_member'],$ids['menu_other']);$m=new \\App\\Repository\\DmMessageRepository($db);for($i=0;$i<25;$i++)$m->create($cid,$ids['menu_other'],'A letter for the room '.$i,'<p>A letter for the room '.$i.'</p>');$c->touch($cid,gmdate('Y-m-d H:i:s'));echo $cid;`);
 for(const [browserName,type]of [['chromium',chromium],['webkit',webkit]]){
  const browser=await type.launch();const auth=await browser.newContext({baseURL:source.url});const login=await auth.newPage();await login.goto('/login');await login.fill('[name=email]','menu_member@retro.test');await login.fill('[name=password]','password123');await login.click('button[type=submit]');await login.waitForURL(u=>u.pathname!='/login');const cookies=await auth.cookies();await auth.close();
  for(const width of [320,390,860,861,1024,1280,1440]){
   const context=await browser.newContext({baseURL:source.url,viewport:{width,height:844},isMobile:width<=860,hasTouch:width<=860,javaScriptEnabled:false});await context.addCookies(cookies);const page=await context.newPage();
   for(const theme of ['light','dark'])for(const size of ['medium','large']){
    php(source,`$u=(new \\App\\Repository\\UserRepository($db))->findByUsername('menu_member');(new \\App\\Repository\\UserPreferenceRepository($db))->merge((int)$u['id'],['theme'=>'${theme}','font_size'=>'${size}']);`);
    await page.goto('/messages/'+id);await page.evaluate(()=>document.fonts.ready);
    const geometry=await page.evaluate(()=>{const r=s=>document.querySelector(s)?.getBoundingClientRect();const p=document.querySelector('.dm-scroll');return{viewportHeight:innerHeight,documentHeight:document.documentElement.scrollHeight,overflow:document.documentElement.scrollHeight-innerHeight,headerHeight:r('.forum-bar')?.height,subheaderHeight:r('[data-subheader]')?.height??0,railHeight:r('.board-rail')?.height,roomHeight:r('.dm-shell')?.height,lettersHeight:r('.dm-scroll')?.height,lettersFloor:parseFloat(getComputedStyle(p).minHeight),composerBottom:r('.dm-composer')?.bottom,sendBottom:r('.dm-composer .composer-send')?.bottom,theme:document.documentElement.dataset.theme,fontSize:document.documentElement.dataset.fontSize};});
    let outcome='passed',failure=null;try{assert.ok(geometry.documentHeight<=geometry.viewportHeight+1,'conversation adds no page scroll: '+geometry.documentHeight+' <= '+(geometry.viewportHeight+1));}catch(e){outcome='failed';failure=e.message;}
    results.push({source:source.name,browser:browserName,width,javaScriptEnabled:false,...geometry,noPageScrollAssertion:outcome,failure});
    if(width===320&&size==='large')await page.screenshot({path:path.join(out,`${source.name}-${browserName}-${width}-${theme}-${size}-no-js.png`),fullPage:true,animations:'disabled'});
   } await context.close();
  } await browser.close();
 }
}
const baselineHead=execFileSync('git',['rev-parse','HEAD'],{cwd:sources[0].cwd}).toString().trim();execFileSync('git',['diff','--exit-code','HEAD','--'],{cwd:sources[0].cwd});
fs.writeFileSync(path.join(out,'native-room-comparison.json'),JSON.stringify({baselineHead,baselineTrackedFilesUnmodified:true,baselineWorktree:sources[0].cwd,baselineDatabase:sources[0].db,baselinePort:8026,assertion:'documentHeight <= viewportHeight + 1',results},null,2)+'\n');
console.log(JSON.stringify({checks:results.length,failures:results.filter(r=>r.noPageScrollAssertion==='failed').length,currentLimits:results.filter(r=>r.source==='change'&&r.noPageScrollAssertion==='failed')},null,2));
})().catch(e=>{console.error(e);process.exitCode=1;});
