const fs = require('node:fs');
const {chromium, devices} = require('/home/ubuntu/community-forums/tests/browser/node_modules/playwright');
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const SAFE_HEADERS = new Set(['cache-control','content-encoding','content-type','content-length','etag','last-modified','date','server','server-timing','cf-cache-status','cf-ray','x-retroboards-cache','link','age','vary']);
function safeUrl(value, base = 'https://forum.candidary.online') {
 try {
  const url = new URL(value, base);
  if (!['http:','https:'].includes(url.protocol)) return url.protocol + '[omitted]';
  let pathname=url.pathname.replace(/\/t\/(\d+)-[^/]+/g,'/t/$1-[slug]').replace(/\/u\/[^/]+/g,'/u/[account]');
  const query = new URLSearchParams();
  for(const key of ['format','page','unread']) if(url.searchParams.has(key)) query.set(key,url.searchParams.get(key));
  return url.origin+pathname+(query.size?'?'+query:'');
 } catch { return '[unparseable-url]'; }
}
function cleanHeaders(headers) {
 return Object.fromEntries(Object.entries(headers||{}).filter(([name])=>SAFE_HEADERS.has(name.toLowerCase())));
}
function installMeasurementGuards() {
 performance.setResourceTimingBufferSize(3000);
 const safePath = value => {
  try {return new URL(typeof value==='string'?value:value?.url||'',location.href).pathname.replace(/\/t\/(\d+)-[^/]+/g,'/t/$1-[slug]').replace(/\/u\/[^/]+/g,'/u/[account]');}
  catch{return '[unparseable-path]';}
 };
 window.__staffPerf={lcp:[],shifts:[],longtasks:[],unsupported:[],blockedWrites:[]};
 const allowed = (method,value) => {
  method=String(method||'GET').toUpperCase();
  if(method==='GET'||method==='HEAD')return true;
  window.__staffPerf.blockedWrites.push({method,path:safePath(value)});return false;
 };
 const realFetch=window.fetch;
 window.fetch=function(input,options){
  if(!allowed(options?.method||input?.method||'GET',input))return Promise.reject(new TypeError('Non-read request blocked by performance capture'));
  return realFetch.apply(this,arguments);
 };
 const realOpen=XMLHttpRequest.prototype.open;
 XMLHttpRequest.prototype.open=function(method,url){
  if(!allowed(method,url))throw new DOMException('Non-read request blocked by performance capture','SecurityError');
  return realOpen.apply(this,arguments);
 };
 navigator.sendBeacon=function(url){allowed('POST',url);return false;};
 const realSubmit=HTMLFormElement.prototype.submit;
 const realRequestSubmit=HTMLFormElement.prototype.requestSubmit;
 HTMLFormElement.prototype.submit=function(){
  if(!allowed(this.method,this.action))return;
  return realSubmit.apply(this,arguments);
 };
 if(realRequestSubmit)HTMLFormElement.prototype.requestSubmit=function(submitter){
  const method=submitter?.getAttribute('formmethod')||this.method;
  const action=submitter?.getAttribute('formaction')||this.action;
  if(!allowed(method,action))return;
  return realRequestSubmit.apply(this,arguments);
 };
 document.addEventListener('submit',event=>{
  const form=event.target;
  if(!(form instanceof HTMLFormElement))return;
  const method=event.submitter?.getAttribute('formmethod')||form.method;
  const action=event.submitter?.getAttribute('formaction')||form.action;
  if(!allowed(method,action)){event.preventDefault();event.stopImmediatePropagation();}
 },true);
 const brief=el=>!el?null:{tag:el.tagName||null,id:['main','reply'].includes(el.id)||/^p\d+$/.test(el.id)?el.id:null,classes:typeof el.className==='string'?el.className:null,rect:el.getBoundingClientRect?.().toJSON()};
 for(const type of ['largest-contentful-paint','layout-shift','longtask']) {
  try {new PerformanceObserver(list=>{
   for(const entry of list.getEntries()){
    if(type==='largest-contentful-paint')window.__staffPerf.lcp.push({...entry.toJSON(),id:'',element:brief(entry.element)});
    if(type==='layout-shift')window.__staffPerf.shifts.push({...entry.toJSON(),sources:entry.sources.map(source=>({node:brief(source.node),previousRect:source.previousRect.toJSON(),currentRect:source.currentRect.toJSON()}))});
    if(type==='longtask')window.__staffPerf.longtasks.push(entry.toJSON());
   }
  }).observe({type,buffered:true});}catch{window.__staffPerf.unsupported.push(type);}
 }
}
async function main() {
 const [base,output,casesJson]=process.argv.slice(2);
 const cases=JSON.parse(casesJson);
 if(!process.env.PERF_COOKIES)throw new Error('Private authenticated cookie file required');
 const throttle=process.env.PERF_THROTTLE!=='false';
 const cooldown=Number(process.env.PERF_COOLDOWN_MS??30000);
 const defaultHold=Number(process.env.PERF_HOLD_MS??15000);
 const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext({...devices['Pixel 7'],viewport:{width:390,height:844},deviceScaleFactor:2,ignoreHTTPSErrors:false});
  await context.addCookies(JSON.parse(fs.readFileSync(process.env.PERF_COOKIES,'utf8')));
  await context.addInitScript(installMeasurementGuards);
  const page=await context.newPage();
  const cdp=await context.newCDPSession(page);
  await cdp.send('Network.enable');
  if(throttle){
   await cdp.send('Network.emulateNetworkConditions',{offline:false,latency:150,downloadThroughput:1600000/8,uploadThroughput:750000/8,connectionType:'cellular4g'});
   await cdp.send('Emulation.setCPUThrottlingRate',{rate:4});
  }
  let network=[],consoleEvents=[],earlyHints=[],requestMap=new Map();
  cdp.on('Network.requestWillBeSent',event=>{
   const record={requestId:event.requestId,url:safeUrl(event.request.url,base),method:event.request.method,type:event.type,timestamp:event.timestamp,wallTime:event.wallTime,initiator:{type:event.initiator.type,url:event.initiator.url?safeUrl(event.initiator.url,base):undefined,lineNumber:event.initiator.lineNumber}};
   requestMap.set(event.requestId,record);network.push(record);
  });
  cdp.on('Network.requestServedFromCache',event=>{const r=requestMap.get(event.requestId);if(r)r.servedFromCache=true;});
  cdp.on('Network.responseReceivedEarlyHints',event=>earlyHints.push({requestId:event.requestId,headers:cleanHeaders(event.headers)}));
  cdp.on('Network.responseReceived',event=>{const r=requestMap.get(event.requestId);if(r)r.response={url:safeUrl(event.response.url,base),status:event.response.status,headers:cleanHeaders(event.response.headers),mimeType:event.response.mimeType,protocol:event.response.protocol,encodedDataLength:event.response.encodedDataLength,fromDiskCache:event.response.fromDiskCache,fromServiceWorker:event.response.fromServiceWorker,timing:event.response.timing};});
  cdp.on('Network.loadingFinished',event=>{const r=requestMap.get(event.requestId);if(r)r.finished={timestamp:event.timestamp,encodedDataLength:event.encodedDataLength};});
  cdp.on('Network.loadingFailed',event=>{const r=requestMap.get(event.requestId);if(r)r.failed={errorText:event.errorText,blockedReason:event.blockedReason,canceled:event.canceled};});
  page.on('console',message=>consoleEvents.push({type:message.type(),category:/Content Security Policy/i.test(message.text())?'csp':'message-content-omitted'}));
  page.on('pageerror',()=>consoleEvents.push({type:'pageerror',category:'error-content-omitted'}));
  const result={capturedAt:new Date().toISOString(),browser:await browser.version(),base:safeUrl(base),privacy:'No DOM text, page titles, account names, response bodies, request headers, cookies, screenshots, or credentials recorded. Topic slugs and account paths redacted.',writeProtection:'GET/HEAD allowed. Init-script guards block non-read fetch, XHR, sendBeacon, programmatic form submissions and submit events. No context.route or cache disabling.',accountScope:'Authenticated staff account; role inferred from rendered navigation controls, not assumed to match an ordinary member.',profile:{viewport:{width:390,height:844},deviceScaleFactor:2,isMobile:true,hasTouch:true,cpuSlowdown:throttle?4:1,network:throttle?{latencyMs:150,downloadBitsPerSecond:1600000,uploadBitsPerSecond:750000}:'unthrottled',cache:'new context initially; same context subsequent visits',holdAfterLoadMs:defaultHold,navigationIntervalMs:cooldown},samples:[]};
  let lastStart=0;
  for(const sampleCase of cases){
   if(lastStart)await wait(Math.max(0,cooldown-(Date.now()-lastStart)));
   network=[];consoleEvents=[];earlyHints=[];requestMap=new Map();
   lastStart=Date.now();const started=new Date().toISOString();
   const response=await page.goto(new URL(sampleCase.path,base).href,{waitUntil:'load',timeout:180000});
   const hold=sampleCase.holdMs??defaultHold;
   await wait(hold);
   const data=await page.evaluate(()=>({
    timeOrigin:performance.timeOrigin,url:location.href,navigation:performance.getEntriesByType('navigation').map(x=>x.toJSON()),paint:performance.getEntriesByType('paint').map(x=>x.toJSON()),resources:performance.getEntriesByType('resource').map(x=>x.toJSON()),observed:window.__staffPerf,
    auth:{hasDataUser:document.body.hasAttribute('data-user'),hasLogoutForm:!!document.querySelector('form[action="/logout"]'),hasAccountSettingsLink:!!document.querySelector('a[href="/settings/account"]'),hasAdminNavigation:!!document.querySelector('a[href="/admin"]'),hasModeratorNavigation:!!document.querySelector('a[href="/mod/reports"]'),hasBell:!!document.querySelector('[data-bell]'),hasPresence:!!document.querySelector('[data-presence]')},
    unread:{boundaryCount:document.querySelectorAll('[data-first-unread-boundary]').length,firstUnreadPostCount:document.querySelectorAll('[data-first-unread="1"]').length,boundaries:[...document.querySelectorAll('[data-first-unread-boundary]')].map(el=>({rect:el.getBoundingClientRect().toJSON()}))},
    composer:{inputs:document.querySelectorAll('.composer-input').length,richEditors:document.querySelectorAll('.wysiwyg-composer .ProseMirror').length,dockMarked:document.querySelectorAll('[data-composer-dock="1"]').length},
    stylesheets:[...document.querySelectorAll('link[rel="stylesheet"]')].map(el=>({href:el.href,media:el.media})),scripts:[...document.scripts].map(el=>({src:el.src,defer:el.defer,async:el.async,type:el.type})),fonts:[...document.fonts].map(font=>({family:font.family,weight:font.weight,style:font.style,status:font.status})),images:[...document.images].map(el=>({src:el.currentSrc,loading:el.loading,complete:el.complete,naturalWidth:el.naturalWidth,naturalHeight:el.naturalHeight,rect:el.getBoundingClientRect().toJSON()})),documentHidden:document.hidden
   }));
   const redactUrls=(value,key='')=>{
    if(Array.isArray(value))return value.map(x=>redactUrls(x,key));
    if(value&&typeof value==='object')return Object.fromEntries(Object.entries(value).map(([k,v])=>[k,redactUrls(v,k)]));
    if(typeof value==='string'&&['url','name','src','href','containerSrc'].includes(key)&&/^https?:/.test(value))return safeUrl(value,base);
    return value;
   };
   const safeData=redactUrls(data);
   const shifts=data.observed.shifts.filter(x=>!x.hadRecentInput);let cls=0,total=0,start=0,previous=0;
   for(const shift of shifts){if(shift.startTime-previous>1000||shift.startTime-start>5000){total=0;start=shift.startTime;}total+=shift.value;cls=Math.max(cls,total);previous=shift.startTime;}
   const nav=data.navigation[0];const lcp=data.observed.lcp.at(-1);
   const summary={ttfbMs:nav.finalResponseHeadersStart||nav.responseStart,firstResponseStartMs:nav.responseStart,finalResponseHeadersStartMs:nav.finalResponseHeadersStart,firstInterimResponseStartMs:nav.firstInterimResponseStart,htmlDownloadMs:nav.responseEnd-(nav.finalResponseHeadersStart||nav.responseStart),fcpMs:data.paint.find(x=>x.name==='first-contentful-paint')?.startTime,lcpMs:lcp?.startTime,lcpElement:lcp?.element,cls,documentResponseStatus:response?.status(),authVerified:data.auth.hasDataUser&&data.auth.hasLogoutForm&&data.auth.hasAccountSettingsLink,staffNavigation:data.auth.hasAdminNavigation||data.auth.hasModeratorNavigation,firstUnreadBoundary:data.unread.boundaryCount>0,resourceTransferBytes:data.resources.reduce((n,r)=>n+r.transferSize,0),resourceEncodedBodyBytes:data.resources.reduce((n,r)=>n+r.encodedBodySize,0),navigationEncodedBodyBytes:nav.encodedBodySize,resources:data.resources.length,polls:data.resources.filter(r=>/\/presence\?|\/notifications\/bell\?/.test(r.name)).map(r=>({url:safeUrl(r.name,base),startTime:r.startTime,duration:r.duration,responseStart:r.responseStart,responseEnd:r.responseEnd})),longTaskTotalMs:data.observed.longtasks.reduce((n,r)=>n+r.duration,0),longTaskBlockingMs:data.observed.longtasks.reduce((n,r)=>n+Math.max(0,r.duration-50),0),blockedWrites:data.observed.blockedWrites,observedNonReadRequests:network.filter(r=>!['GET','HEAD'].includes(r.method)).map(r=>({method:r.method,path:new URL(r.url).pathname}))};
   result.samples.push({label:sampleCase.label,started,holdAfterLoadMs:hold,summary,...safeData,network,earlyHints,consoleEvents});
   fs.writeFileSync(output,JSON.stringify(result,null,2)+'\n');
   console.log(JSON.stringify({label:sampleCase.label,started,...summary}));
   if(!summary.authVerified)throw new Error('Authenticated page controls not confirmed; stopped before next navigation');
   if(summary.observedNonReadRequests.length)throw new Error('Unexpected non-read request observed; stopped before next navigation');
  }
 }finally{await browser.close();}
}
module.exports={installMeasurementGuards,safeUrl};
if(require.main===module)main().catch(error=>{console.error(error.message);process.exit(1);});
