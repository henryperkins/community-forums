const fs = require('node:fs');
const path = require('node:path');
const { chromium, devices } = require('/home/ubuntu/community-forums/tests/browser/node_modules/playwright');
const args = process.argv.slice(2);
const base = args[0];
const output = args[1];
const cases = JSON.parse(args[2]);
const throttle = process.env.PERF_THROTTLE !== 'false';
const cooldown = Number(process.env.PERF_COOLDOWN_MS ?? 30000);
const hold = Number(process.env.PERF_HOLD_MS ?? 7000);
const cleanHeaders = h => Object.fromEntries(Object.entries(h || {}).filter(([k]) => !['set-cookie','cookie','authorization','proxy-authorization'].includes(k.toLowerCase())));
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
 const browser = await chromium.launch({headless:true});
 const context = await browser.newContext({...devices['Pixel 7'],viewport:{width:390,height:844}, deviceScaleFactor:2,ignoreHTTPSErrors:false});
 if (process.env.PERF_COOKIES) await context.addCookies(JSON.parse(fs.readFileSync(process.env.PERF_COOKIES,'utf8')));
 await context.addInitScript(() => {
  performance.setResourceTimingBufferSize(3000);
  const brief = el => !el ? null : {tag:el.tagName,id:el.id,classes:String(el.className || ''),text:el.textContent?.trim().slice(0,160)};
  window.__perf = {lcp:[],shifts:[],longtasks:[],unsupported:[]};
  for(const type of ['largest-contentful-paint','layout-shift','longtask']) {
   try { new PerformanceObserver(list => {
    for(const e of list.getEntries()) {
     if(type === 'largest-contentful-paint') window.__perf.lcp.push({...e.toJSON(),element:brief(e.element)});
     if(type === 'layout-shift') window.__perf.shifts.push({...e.toJSON(),sources:e.sources.map(x=>({node:brief(x.node),previousRect:x.previousRect.toJSON(),currentRect:x.currentRect.toJSON()}))});
     if(type === 'longtask') window.__perf.longtasks.push(e.toJSON());
    }
   }).observe({type,buffered:true}); } catch(e) { window.__perf.unsupported.push(type); }
  }
 });
 const page = await context.newPage();
 const cdp = await context.newCDPSession(page);
 await cdp.send('Network.enable');
 if(throttle) {
  await cdp.send('Network.emulateNetworkConditions',{offline:false,latency:150,downloadThroughput:1600000/8,uploadThroughput:750000/8,connectionType:'cellular4g'});
  await cdp.send('Emulation.setCPUThrottlingRate',{rate:4});
 }
 let network=[], consoleMessages=[],earlyHints=[];
 let requestMap=new Map();
 cdp.on('Network.requestWillBeSent',e=>{
  const r={requestId:e.requestId,url:e.request.url,method:e.request.method,type:e.type,timestamp:e.timestamp,wallTime:e.wallTime,initiator:{type:e.initiator.type,url:e.initiator.url,lineNumber:e.initiator.lineNumber,stack:e.initiator.stack?.callFrames?.slice(0,6)}};
  requestMap.set(e.requestId,r);network.push(r);
 });
 cdp.on('Network.requestServedFromCache',e=>{const r=requestMap.get(e.requestId);if(r)r.servedFromCache=true;});
 cdp.on('Network.responseReceivedEarlyHints',e=>earlyHints.push({requestId:e.requestId,headers:cleanHeaders(e.headers)}));
 cdp.on('Network.responseReceived',e=>{const r=requestMap.get(e.requestId);if(r)r.response={url:e.response.url,status:e.response.status,headers:cleanHeaders(e.response.headers),mimeType:e.response.mimeType,protocol:e.response.protocol,encodedDataLength:e.response.encodedDataLength,fromDiskCache:e.response.fromDiskCache,fromServiceWorker:e.response.fromServiceWorker,timing:e.response.timing,remoteIPAddress:e.response.remoteIPAddress};});
 cdp.on('Network.loadingFinished',e=>{const r=requestMap.get(e.requestId);if(r)r.finished={timestamp:e.timestamp,encodedDataLength:e.encodedDataLength};});
 cdp.on('Network.loadingFailed',e=>{const r=requestMap.get(e.requestId);if(r)r.failed=e;});
 page.on('console',m=>consoleMessages.push({type:m.type(),text:m.text()}));
 page.on('pageerror',e=>consoleMessages.push({type:'pageerror',text:String(e)}));
 const result={capturedAt:new Date().toISOString(),browser:await browser.version(),base,profile:{viewport:{width:390,height:844},deviceScaleFactor:2,isMobile:true,hasTouch:true,cpuSlowdown:throttle?4:1,network:throttle?{latencyMs:150,downloadBitsPerSecond:1600000,uploadBitsPerSecond:750000}:'unthrottled',cache:'new context initially, same context subsequent visits',holdAfterLoadMs:hold,navigationIntervalMs:cooldown},samples:[]};
 let lastStart=0;
 for(const c of cases) {
  if(lastStart)await wait(Math.max(0,cooldown-(Date.now()-lastStart)));
  network=[]; consoleMessages=[]; earlyHints=[]; requestMap=new Map();
  lastStart=Date.now();
  const started=new Date().toISOString();
  const response=await page.goto(new URL(c.path,base).href,{waitUntil:'load',timeout:180000});
  await wait(hold);
  const data=await page.evaluate(()=>({
   timeOrigin:performance.timeOrigin,url:location.href,title:document.title,navigation:performance.getEntriesByType('navigation').map(e=>e.toJSON()),paint:performance.getEntriesByType('paint').map(e=>e.toJSON()),resources:performance.getEntriesByType('resource').map(e=>e.toJSON()),observed:window.__perf,
   stylesheets:[...document.querySelectorAll('link[rel="stylesheet"]')].map(el=>({href:el.href,media:el.media})), scripts:[...document.scripts].map(el=>({src:el.src,defer:el.defer,async:el.async,type:el.type})), fonts:[...document.fonts].map(x=>({family:x.family,weight:x.weight,style:x.style,status:x.status})),links:[...document.querySelectorAll('a[href^="/t/"]')].slice(0,10).map(el=>({href:el.getAttribute('href'),text:el.textContent.trim().slice(0,160)})),images:[...document.images].map(el=>({src:el.currentSrc,loading:el.loading,complete:el.complete,naturalWidth:el.naturalWidth,naturalHeight:el.naturalHeight,rect:el.getBoundingClientRect().toJSON()})),hasBell:!!document.querySelector('[data-bell]'),hasPresence:!!document.querySelector('[data-presence]'),documentHidden:document.hidden,
  }));
  const shifts=data.observed.shifts.filter(x=>!x.hadRecentInput);let max=0,total=0,start=0,prev=0;
  for(const s of shifts){if(s.startTime-prev>1000||s.startTime-start>5000){total=0;start=s.startTime;}total+=s.value;max=Math.max(max,total);prev=s.startTime;}
  const summary={ttfbMs:data.navigation[0].finalResponseHeadersStart || data.navigation[0].responseStart,firstResponseStartMs:data.navigation[0].responseStart,finalResponseHeadersStartMs:data.navigation[0].finalResponseHeadersStart,firstInterimResponseStartMs:data.navigation[0].firstInterimResponseStart,htmlDownloadMs:data.navigation[0].responseEnd-(data.navigation[0].finalResponseHeadersStart || data.navigation[0].responseStart),fcpMs:data.paint.find(e=>e.name==='first-contentful-paint')?.startTime,lcpMs:data.observed.lcp.at(-1)?.startTime,lcpElement:data.observed.lcp.at(-1)?.element,cls:max,documentResponseStatus:response?.status(),resourceTransferBytes:data.resources.reduce((n,r)=>n+r.transferSize,0),resourceEncodedBodyBytes:data.resources.reduce((n,r)=>n+r.encodedBodySize,0),navigationEncodedBodyBytes:data.navigation[0].encodedBodySize,resources:data.resources.length,polls:data.resources.filter(r=>/\/presence\?|\/notifications\/bell\?/.test(r.name)).map(r=>({url:r.name,startTime:r.startTime,duration:r.duration,responseStart:r.responseStart,responseEnd:r.responseEnd})),longTaskTotalMs:data.observed.longtasks.reduce((n,r)=>n+r.duration,0),longTaskBlockingMs:data.observed.longtasks.reduce((n,r)=>n+Math.max(0,r.duration-50),0)};
  const sample={label:c.label,started,summary,...data,network,earlyHints,consoleMessages};result.samples.push(sample);
  fs.writeFileSync(output,JSON.stringify(result,null,2)+'\n');
  await page.screenshot({path:output.replace(/\.json$/,'')+'-'+c.label+'.png'});
  console.log(JSON.stringify({label:c.label,started,...summary}));
 }
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
