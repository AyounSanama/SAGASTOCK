import { spawn } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import assert from 'node:assert/strict';

const port = 19387;
const chrome = spawn(process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', '--no-first-run', '--no-default-browser-check',
  `--remote-debugging-port=${port}`, `--user-data-dir=${resolve('.tmp/ui-chrome-profile')}`, 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
let ws;
const sleep = ms => new Promise(r => setTimeout(r, ms));
try {
  let tabs;
  for (let i = 0; i < 60; i++) {
    try { tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); if (tabs.length) break; } catch {}
    await sleep(250);
  }
  assert(tabs?.length, 'Chrome did not start');
  ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise((ok, fail) => { ws.onopen = ok; ws.onerror = fail; });
  let id = 0; const pending = new Map();
  ws.onmessage = event => {
    const result = JSON.parse(event.data);
    if (result.id && pending.has(result.id)) {
      const [ok, fail] = pending.get(result.id); pending.delete(result.id);
      result.error ? fail(new Error(JSON.stringify(result.error))) : ok(result.result);
    }
  };
  const rpc = (method, params = {}) => new Promise((ok, fail) => {
    const key = ++id; pending.set(key, [ok, fail]); ws.send(JSON.stringify({ id: key, method, params }));
  });
  const evaluate = async expression => {
    const r = await rpc('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails));
    return r.result.value;
  };
  await rpc('Page.enable');
  await rpc('Page.navigate', { url: pathToFileURL(resolve('.tmp/ui-web-fixture.html')).href });
  for (let i = 0; i < 60; i++) {
    if (await evaluate("!!document.querySelector('.form-stepper')")) break;
    await sleep(250);
  }
  assert(await evaluate("!!document.querySelector('.form-stepper')"), 'Workflow did not initialize');
  await evaluate("document.querySelector('[data-sheet-open]').click()");
  const sizes = [[360,640], [800,1024], [1366,768], [1440,900], [1920,1080]];
  for (const [width, height] of sizes) {
    await rpc('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
    await sleep(220);
    const rect = await evaluate(`(() => { const p = document.querySelector('.form-sheet-panel').getBoundingClientRect(); return {x:p.x,y:p.y,width:p.width,height:p.height}; })()`);
    assert(Math.abs(rect.x + rect.width/2 - width/2) < 2, 'Horizontal centering');
    assert(Math.abs(rect.y + rect.height/2 - height/2) < 2, 'Vertical centering');
    assert(rect.width <= 720 && rect.height <= height * .91, 'Responsive bounds');
    console.log(`PASS centered ${width}x${height}`);
  }
  await evaluate("document.querySelectorAll('.form-stepper button')[3].click()");
  assert.equal(await evaluate("document.querySelector('[aria-current=step]').textContent"), '1. Informations');
  await evaluate("document.querySelector('[name=name]').value='Centre test';document.querySelector('[name=code]').value='TEST';document.querySelectorAll('.form-stepper button')[3].click()");
  assert.equal(await evaluate("document.querySelector('[aria-current=step]').textContent"), '2. Classification');
  await evaluate("document.querySelector('[name=care_level]').value='primary';document.querySelectorAll('.form-stepper button')[3].click()");
  assert.equal(await evaluate("document.querySelector('[aria-current=step]').textContent"), '4. Résumé');
  assert(await evaluate("document.querySelector('.form-summary').textContent.includes('Centre test')"));
  assert(await evaluate("document.querySelector('.form-summary').textContent.includes('Projet de test')"));
  assert.equal(await evaluate("document.querySelector('button[type=submit]').form === document.querySelector('form')"), true);
  await evaluate("window.confirm=()=>false;document.querySelector('[data-sheet-close]').click()");
  assert(await evaluate("document.querySelector('dialog').open"), 'Dirty draft preserved');
  await evaluate("document.querySelectorAll('.form-stepper button')[0].click();document.querySelector('[data-form-step]').style.minHeight='1200px'");
  const footerY = await evaluate("document.querySelector('.form-sheet-footer').getBoundingClientRect().y");
  await evaluate("document.querySelector('.form-sheet-body').scrollTop=1000");
  assert.equal(await evaluate("document.querySelector('.form-sheet-footer').getBoundingClientRect().y"), footerY);
  await evaluate("document.querySelector('[data-form-step]').style.minHeight='';document.querySelector('.form-sheet-body').scrollTop=0");
  await rpc('Emulation.setDeviceMetricsOverride', { width: 800, height: 1024, deviceScaleFactor: 1, mobile: false });
  await sleep(220);
  const screenshot = await rpc('Page.captureScreenshot', { format: 'png' });
  await writeFile('.tmp/ui-centered-sheet-tablet.png', Buffer.from(screenshot.data, 'base64'));
  await evaluate("window.confirm=()=>true;document.querySelector('[data-sheet-close]').click()");
  assert.equal(await evaluate("document.querySelector('dialog').open"), false);
  console.log('PASS validation, summary, fixed footer, form ownership and dirty-draft protection');
} finally {
  ws?.close(); chrome.kill();
}
