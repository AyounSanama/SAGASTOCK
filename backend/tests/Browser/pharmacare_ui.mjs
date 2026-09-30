import { spawn } from 'node:child_process';
import { writeFile, mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

const origin = 'http://127.0.0.1:18765';
const port = 19388;
const chrome = spawn(process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', '--no-proxy-server', '--no-first-run', '--no-default-browser-check',
  `--remote-debugging-port=${port}`, `--user-data-dir=${resolve('.tmp/pharmacare-ui-chrome')}`, 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
let ws;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const errors = [];
const results = [];
await mkdir('.tmp/ui-screenshots', { recursive: true });
try {
  let tabs;
  for (let i=0;i<60;i++) {
    try { tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); if (tabs.length) break; } catch {}
    await sleep(250);
  }
  ws = new WebSocket(tabs[0].webSocketDebuggerUrl);
  await new Promise(r => ws.addEventListener('open', r, { once:true }));
  let id=0; const pending = new Map();
  ws.addEventListener('message', event => {
    const message=JSON.parse(event.data);
    if (message.method==='Runtime.exceptionThrown') errors.push(message.params.exceptionDetails);
    if (message.id) { const entry=pending.get(message.id); pending.delete(message.id); message.error?entry.reject(message.error):entry.resolve(message.result); }
  });
  const rpc=(method,params={})=>new Promise((resolve,reject)=>{ const key=++id; pending.set(key,{resolve,reject}); ws.send(JSON.stringify({id:key,method,params})); });
  const evaluate=async expression=>{
    const result=await rpc('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
    if(result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const until=async expression=>{
    for(let i=0;i<300;i++){if(await evaluate(expression))return;await sleep(100);}
    throw Error(`Timed out: ${expression}: ${await evaluate("JSON.stringify({url:location.href,state:document.readyState,text:document.body?.innerText.slice(0,300)})")}`);
  };
  const go=async path=>{await evaluate('window.__previousDocument=true');await rpc('Page.navigate',{url:origin+path});await until("!window.__previousDocument && document.readyState==='complete'");};
  const screenshot=async name=>{const shot=await rpc('Page.captureScreenshot',{format:'png'});await writeFile(`.tmp/ui-screenshots/${name}.png`,Buffer.from(shot.data,'base64'));};
  await rpc('Page.enable'); await rpc('Runtime.enable'); await rpc('Network.enable');
  for (const role of (process.env.UI_ROLE ? [process.env.UI_ROLE] : ['sago_admin','coordination_admin','project_admin','site_admin','site_user'])) {
    await rpc('Network.clearBrowserCookies');
    await rpc('Emulation.setDeviceMetricsOverride',{width:1920,height:1080,deviceScaleFactor:1,mobile:false});
    await go('/login');
    await evaluate(`document.querySelector('[name=login]').value='${role}@ui.example';document.querySelector('[name=password]').value='UiBrowser123!';document.querySelector('.auth-form').requestSubmit()`);
    await until("location.pathname.endsWith('/dashboard') && document.readyState==='complete' && !!document.querySelector('[data-notifications]')");
    for(const [width,height,label] of [[1920,1080,'desktop'],[1366,768,'laptop'],[800,1024,'tablet'],[360,740,'small']]) {
      await rpc('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false}); await sleep(300);
      const dimensions=await evaluate(`({width:innerWidth,scroll:document.documentElement.scrollWidth,title:getComputedStyle(document.querySelector('main h1')).fontSize,kpis:[...document.querySelectorAll('.app-kpi-card')].map(e=>Math.round(e.getBoundingClientRect().height)),rows:[...document.querySelectorAll('.app-kpi-card')].map(e=>({y:Math.round(e.getBoundingClientRect().y),h:Math.round(e.getBoundingClientRect().height)}))})`);
      assert(dimensions.scroll<=width+1,`${role} ${label} overflow ${JSON.stringify(dimensions)}`);
      assert.equal(dimensions.title,'22px');
      for (const row of dimensions.rows) assert(dimensions.rows.filter(item=>item.y===row.y).every(item=>Math.abs(item.h-row.h)<=1),'KPI row heights');
      await screenshot(`${role}-${label}`);results.push({role,label,...dimensions});
    }
    await evaluate("document.querySelector('.sidebar-toggle').click()");
    await until("document.querySelector('.sidebar-toggle').getAttribute('aria-expanded')==='true'");
    await sleep(300);
    assert(await evaluate("document.querySelector('.app-sidebar').getBoundingClientRect().x>=-1"),'Mobile navigation opens');
    await rpc('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await until("!document.body.classList.contains('mobile-menu-open')");
    await evaluate("document.querySelector('.profile-menu summary').click()");
    const menu=await evaluate("(()=>{const r=document.querySelector('.profile-menu-panel').getBoundingClientRect();return {left:r.left,right:r.right}})()");
    assert(menu.left>=0&&menu.right<=360,'Profile menu viewport bounds');
    await rpc('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    assert(await evaluate("!document.querySelector('.profile-menu').open"),'Profile closes on Escape');
    await evaluate("document.querySelector('[data-notifications] summary').click()");
    await until("document.querySelectorAll('.notification-item').length>0");
    await screenshot(`${role}-notifications`);
    const unread=await evaluate("Number(document.querySelector('[data-notification-count]').textContent)");
    if(unread>0) {
      await evaluate("document.querySelector('.notification-item button').click()");
      await until("document.querySelector('[data-notification-count]').hidden");
      assert.equal(await evaluate("document.querySelector('.notification-item small').textContent"),'Lue');
    }
    await evaluate("document.querySelector('[data-notification-read-all]').click()");
    await until("!document.querySelector('[data-notification-read-all]').disabled");
    await rpc('Network.setBlockedURLs',{urls:['*profile/notifications*']});
    await evaluate("document.querySelector('[data-notification-refresh]').click()");
    await until("document.querySelector('[data-notification-status]').textContent.includes('fetch') || document.querySelector('[data-notification-status]').textContent.includes('Impossible')");
    await rpc('Network.setBlockedURLs',{urls:[]});
    await evaluate("document.querySelector('[data-notification-refresh]').click()");
    await until("document.querySelector('[data-notification-status]').textContent===''");
    await evaluate("document.querySelector('[data-notifications] summary').click()");
    const pages = role==='sago_admin' ? ['/configuration/organization','/configuration/platform-standards','/profile']
      : role==='coordination_admin' ? ['/missions','/projects','/funding','/standard-lists','/profile']
      : role==='project_admin' ? ['/projects','/health-facilities','/users','/standard-lists','/profile']
      : ['/stocks','/receipts','/dispensations','/inventories','/orders','/reports','/standard-lists','/profile'];
    for(const path of pages) {
      await go(path);
      assert(await evaluate("!!document.querySelector('.app-shell-topbar')"),`${role} ${path} missing shell`);
      if(path!=='/reports') assert(await evaluate("!document.querySelector('.module-placeholder')"),`${role} ${path} must use its existing workspace`);
      for(const [width,height] of [[1920,1080],[1366,768],[800,1024],[360,740]]) {
        await rpc('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});await sleep(300);
        const overflow=await evaluate('document.documentElement.scrollWidth>innerWidth+1');
        const topbar=await evaluate("[document.querySelector('.sidebar-toggle'),document.querySelector('.notification-center summary'),document.querySelector('.profile-menu summary')].map(e=>{const r=e.getBoundingClientRect();return r.y+r.height/2})");
        assert(Math.max(...topbar)-Math.min(...topbar)<2,`${role} ${path} topbar alignment at ${width}: ${topbar}`);
        if(width===360) {
          const left=await evaluate('document.querySelector("main").getBoundingClientRect().x');
          if(left>=30) console.log(await evaluate('[document.body,document.querySelector("main"),document.querySelector(".portal-workspace")].filter(Boolean).map(e=>({cls:e.className,x:e.getBoundingClientRect().x,padding:getComputedStyle(e).padding,margin:getComputedStyle(e).margin}))'));
          assert(left<30,`${role} ${path} reserves hidden sidebar space`);
        }
        await screenshot(`${role}-${path.replaceAll('/','_')}-${width}`);
        if(overflow) console.log(await evaluate("[...document.querySelectorAll('main *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,12).map(e=>({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width,right:e.getBoundingClientRect().right}))"));
        assert(!overflow,`${role} ${path} overflow at ${width}`);
        results.push({role,path,width,height,overflow});
      }
    }
    if(role.startsWith('site_')) {
      await go('/stocks');
      assert(await evaluate("!!document.querySelector('a[rel=next]')"),'Stock pagination has second page');
      await evaluate("document.querySelector('a[rel=next]').click()");
      await until("location.search.includes('page=2') && document.readyState==='complete'");
      await screenshot(`${role}-stock-pagination-small`);
      await evaluate("document.querySelector('input[name=search]').value='ABSENT-STABILISATION';document.querySelector('input[name=search]').form.requestSubmit()");
      await until("location.search.includes('ABSENT-STABILISATION') && document.readyState==='complete'");
      assert(await evaluate("document.querySelector('input[name=search]').value==='ABSENT-STABILISATION'"),'Stock search preserved');
      if(role==='site_admin') {
        await evaluate("document.querySelector('[data-sheet-open=movement-create-sheet]').click()");
        await until("document.querySelector('#movement-create-sheet').open");
        await sleep(250);
        const sheet=await evaluate("(()=>{const r=document.querySelector('#movement-create-sheet .form-sheet-panel').getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height}})()");
        assert(Math.abs(sheet.x+sheet.w/2-180)<2&&sheet.y>=0&&sheet.h<=740*.91,'Stock sheet centered');
        await screenshot(`${role}-stock-form-small`);
      }
      await go('/users');
      assert(await evaluate("document.body.innerText.includes('Accès refusé')"),'Site forbidden users page');
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'403 responsive');
      await screenshot(`${role}-403-small`);
    }
    if(role==='coordination_admin') {
      await go('/projects');
      await evaluate("document.querySelector('[data-sheet-open=project-create-sheet]').click()");
      await until("document.querySelector('#project-create-sheet').open");
      await sleep(250);
      await screenshot(`${role}-form-small`);
      const rect=await evaluate("(()=>{const r=document.querySelector('#project-create-sheet .form-sheet-panel').getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height}})()");
      assert(rect.x>=10&&rect.x+rect.w<=350&&rect.h<=740*.91,JSON.stringify(rect));
    }
    console.log(`PASS ${role}: four viewports, notification list/read/badge, JavaScript`);
  }
  assert.deepEqual(errors,[],'JavaScript runtime exceptions');
  await writeFile(process.env.UI_ROLE ? `.tmp/ui-browser-results-${process.env.UI_ROLE}.json` : '.tmp/ui-browser-results.json',JSON.stringify({results,errors},null,2));
} finally { ws?.close(); chrome.kill(); }
