'use strict';
/** STILLWIRED evidence-driven, offline jury demo. No network, npm, LLM or mutation. Node 18+. */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const root = process.cwd();
const evidenceDir = path.join(root, 'var', 'evidence');
const outDir = path.join(root, 'dist');

function assert(ok, msg) { if (!ok) throw new Error(`EVIDENCE_REJECTED: ${msg}`); }
function digest(buf) { return crypto.createHash('sha256').update(buf).digest('hex'); }
function verified(file) {
  const p = path.join(evidenceDir, file);
  assert(fs.existsSync(p) && fs.existsSync(p+'.sha256'), `Missing ${file} or .sha256: rerun C1/C2 on the real project`);
  const bytes = fs.readFileSync(p);
  const expected = fs.readFileSync(p+'.sha256','utf8').trim().split(/\s+/)[0].toLowerCase();
  assert(/^[0-9a-f]{64}$/.test(expected), `Malformed sidecar for ${file}`);
  assert(digest(bytes) === expected, `SHA-256 mismatch for ${file}`);
  return {body:bytes.toString('utf8'), sha256:expected};
}
function json(file) { const v=verified(file); try { return {...v, data:JSON.parse(v.body)}; } catch(e) {throw new Error(`Invalid JSON ${file}: ${e.message}`)} }
function safeText(s) { return String(s||'').replace(/[^\x20-\x7e]/g,'').slice(0,160); }
function validReceipt(r) { return r && typeof r.event_id === 'string' && /^[0-9a-f]{16}$/.test(r.event_id) && ['webhook','scheduled_export'].includes(r.flow) && typeof r.canary_id === 'string'; }
function state(r, f) { return r.observed_states?.[f]; }
function run(){
  const c1 = json('competitive-false-green.json');
  const c1After = json('competitive-truly-closed.json');
  const c2 = json('c2-summary.json');
  const c2Verdict = json('c2-php-verdict.json');
  const c2Trace = verified('c2-agent-tool-events.jsonl');
  const c2Transcript = verified('c2-ai-transcript.txt'); // verify, but NEVER embed model transcript
  const before = c1.data, after=c1After.data, investigation=c2.data, adjudication=c2Verdict.data;
  const flows=['webhook','scheduled_export'];
  assert(before.account_active === false && before.decision === 'EXPOSED', 'C1 false-green must be exposed and human revoked');
  assert(before.status_only_baseline === 'VERIFIED', 'C1 status-only baseline not VERIFIED');
  assert(flows.every(f=>state(before,f)==='disabled'), 'C1 provider metadata must say disabled');
  assert(Array.isArray(before.deliveries) && before.deliveries.length===2 && flows.every(f=>before.deliveries.some(d=>d.flow===f&&validReceipt(d))), 'C1 lacks two delivery receipts');
  assert(after.decision === 'VERIFIED' && after.deliveries?.length===0 && flows.every(f=>state(after,f)==='disabled'), 'C1 post-stop verdict not VERIFIED/zero deliveries');
  assert(investigation.status_only_baseline==='VERIFIED' && investigation.authoritative_verdict==='EXPOSED', 'C2 contradiction invalid');
  assert(investigation.model_output_contains_receipts===true && investigation.forbidden_mutations_observed===false, 'C2 model or safety proof invalid');
  assert(typeof investigation.canary_id==='string' && /^SW-AI-[A-F0-9]{16}$/.test(investigation.canary_id), 'C2 canary invalid');
  assert(Array.isArray(investigation.persisted_deliveries) && investigation.persisted_deliveries.length===2 && flows.every(f=>investigation.persisted_deliveries.some(d=>d.flow===f&&d.canary_id===investigation.canary_id&&validReceipt(d))), 'C2 receipts invalid');
  assert(adjudication.decision==='EXPOSED' && adjudication.status_only_baseline==='VERIFIED' && adjudication.deliveries?.length===2, 'C2 independent PHP verdict invalid');
  assert(flows.every(f=>state(adjudication,f)==='disabled'), 'C2 status metadata not disabled');
  const events=c2Trace.body.split(/\r?\n/).filter(Boolean).map(s=>JSON.parse(s));
  const tools=['webhook_observe_webhook','scheduler_observe_export','webhook_emit_webhook_canary','scheduler_emit_export_canary'];
  assert(tools.every(tool=>events.some(e=>e.tool===tool&&e.status==='succeeded')&&events.some(e=>e.tool===tool&&e.status==='requested')), 'Not all 4 MCP tools have requested+succeeded audit events');
  assert(events.every(e=>tools.includes(e.tool)&&['requested','succeeded'].includes(e.status)), 'Unexpected tool or status in audit trace');
  assert(investigation.persisted_deliveries.every(d=>c2Transcript.body.includes(d.event_id)), 'Model transcript omitted a C2 receipt');
  assert(investigation.persisted_deliveries.every(d=>adjudication.deliveries.some(x=>x.flow===d.flow && validReceipt(x))), 'PHP verdict lacks a receipt for each flow');
  const hashes=Object.fromEntries([
    ['competitive-false-green.json',c1],['competitive-truly-closed.json',c1After],
    ['c2-summary.json',c2],['c2-php-verdict.json',c2Verdict],
    ['c2-agent-tool-events.jsonl',c2Trace],['c2-ai-transcript.txt',c2Transcript],
  ].map(([name,entry])=>[name,entry.sha256]));
  const manifest={
    title:'STILLWIRED — False Green',type:'SYNTHETIC_LAB_EVIDENCE',
    caution:'C1 and C2 are separate controlled runs. No real vendor or customer data. SHA-256 sidecars are local integrity checks, NOT source authentication.',
    c1:{status_only:before.status_only_baseline, verdict:before.decision, reported_states:before.observed_states,receipts:before.deliveries.map(d=>({flow:d.flow,event_id:d.event_id,canary_id:d.canary_id,timestamp_utc:d.timestamp_utc||null})),after_stop:{decision:after.decision,new_deliveries:after.deliveries.length}},
    c2:{status_only:investigation.status_only_baseline, verdict:adjudication.decision,canary_id:investigation.canary_id,receipt_count:investigation.persisted_deliveries.length,receipts:investigation.persisted_deliveries.map(d=>({flow:d.flow,event_id:d.event_id,canary_id:d.canary_id,timestamp_utc:d.timestamp_utc||null})), tool_names:tools, trace:events.map(e=>({tool:e.tool,status:e.status,timestamp_utc:e.timestamp_utc||null})), model_receipts_cited:true, agent_mutations_observed:false},
    evidence_sha256:hashes,
    limitations:['Enumerated synthetic integrations only','No guarantee regarding delayed queues, retries, or unknown credentials','Approval tokens are a lab mechanism, not production IAM','Baseline is status-only, not a best-in-class security tool']
  };
  fs.mkdirSync(outDir,{recursive:true});
  const manifestPath=path.join(outDir,'stillwired-jury-evidence.json');
  fs.writeFileSync(manifestPath,JSON.stringify(manifest,null,2)+'\n');
  const embedded=JSON.stringify(manifest).replace(/</g,'\\u003c').replace(/>/g,'\\u003e').replace(/&/g,'\\u0026').replace(/\u2028/g,'\\u2028').replace(/\u2029/g,'\\u2029');
  const html=`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>STILLWIRED — The dashboard is green. The data is still leaving.</title>
<style>
:root{font:16px/1.55 Inter,Segoe UI,system-ui,sans-serif;color:#eaf2ff;background:#070c14;color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 80% 0%,#163151 0,transparent 46%),#070c14}main{max-width:1240px;margin:auto;padding:42px 28px 75px}.head{display:flex;justify-content:space-between;align-items:center;gap:20px}.brand{font-size:20px;font-weight:900;letter-spacing:.18em;color:#eaf4ff}.pill{display:inline-flex;align-items:center;gap:8px;border:1px solid #44617b;color:#b7cde5;padding:7px 12px;border-radius:30px;font-size:12px;text-transform:uppercase;letter-spacing:.08em}.dot{width:7px;height:7px;border-radius:50%;background:#36ddbb}.kicker{margin-top:70px;color:#7ba8cf;font-weight:750;letter-spacing:.2em;font-size:12px;text-transform:uppercase}h1{font-size:clamp(42px,6.4vw,90px);line-height:1.05;letter-spacing:-.065em;margin:14px 0;max-width:1080px}h1 span{color:#fd8e9e}p{color:#a9bdd3;max-width:930px}.lead{font-size:20px;max-width:860px}.step-nav{display:flex;gap:9px;margin:38px 0 22px;flex-wrap:wrap}button{font:inherit;border-radius:12px;padding:10px 16px;border:1px solid #36516a;color:#a8c0d5;background:#0e1a2a;cursor:pointer}button:focus-visible{outline:3px solid #72e4d2}button[aria-pressed=true]{background:#173b55;border-color:#51bee3;color:white}.panel{border:1px solid #253950;border-radius:24px;background:linear-gradient(145deg,#132136,#0c1623);padding:26px}.panel[hidden]{display:none}.label{font-size:12px;letter-spacing:.17em;text-transform:uppercase;color:#92acc9;font-weight:800}.stage-title{font-size:clamp(28px,4.4vw,53px);margin:6px 0 22px;line-height:1.13;letter-spacing:-.045em}.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.card{border:1px solid #2f435b;border-radius:18px;background:#0b1623;padding:20px;min-height:165px}.metric{font-size:clamp(30px,3vw,49px);line-height:1.18;font-weight:900;letter-spacing:-.04em;margin:15px 0 8px}.sub{font-size:13px;color:#9ab1ca}.green{color:#59e7ae}.red{color:#ff8195}.blue{color:#78c7ff}.section{margin-top:24px}.section h2{font-size:17px}.receipts{width:100%;border-collapse:collapse;margin-top:12px}th,td{text-align:left;border-bottom:1px solid #263a50;padding:12px 10px;vertical-align:top}th{font-size:11px;letter-spacing:.12em;color:#89a8c5;text-transform:uppercase}td{font-size:14px}code{font:12px/1.5 Consolas,monospace;color:#c0e8ff;overflow-wrap:anywhere}.event{margin:8px 0;padding:10px 14px;border:1px solid #254356;background:#0b1d2b;border-radius:10px;font-family:Consolas,monospace;font-size:13px}.foot{margin:26px 0 0;color:#859db7;font-size:13px}.foot strong{color:#b7cde1}.footer{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:18px}small{color:#8ca3ba}.controls{display:flex;gap:10px}a{color:#79c9ff}ul{color:#adc1d6}li{margin:6px 0}@media(max-width:750px){main{padding:25px 16px 60px}.head{align-items:flex-start}.kicker{margin-top:42px}.cards{grid-template-columns:1fr}.receipts{display:block;overflow-x:auto}h1{font-size:41px}.footer{flex-direction:column}}
</style></head><body><main><div class="head"><div class="brand">STILLWIRED</div><div class="pill"><span class="dot"></span>Synthetic incident / Evidence replay</div></div><div class="kicker">SYMFONY AI + PHP MCP SDK / CONTROL PLANE VS DATA PLANE</div><h1>The dashboard is green.<br><span>The data is still leaving.</span></h1><p class="lead">Both providers report <strong>disabled</strong> after the contractor account is revoked. A controlled canary proves the outbound flows still deliver. An AI investigator explains the contradiction; deterministic PHP decides whether closure is safe.</p><nav class="step-nav" aria-label="Evidence stages"><button type="button" data-step="0" aria-pressed="true">01 / False green</button><button type="button" data-step="1" aria-pressed="false">02 / AI investigation</button><button type="button" data-step="2" aria-pressed="false">03 / Verified stop</button></nav>
<section class="panel" data-panel="0"><div class="label">C1 · controlled false-green run</div><h2 class="stage-title">A green status hides live delivery.</h2><div class="cards"><div class="card"><div class="label">Provider metadata</div><div class="metric green" id="c1-status">—</div><div class="sub">Status-only checker would close the incident</div></div><div class="card"><div class="label">Real synthetic probe</div><div class="metric red" id="c1-verdict">—</div><div class="sub">Independent PHP verifier blocks closure</div></div><div class="card"><div class="label">Persisted receipts</div><div class="metric blue" id="c1-count">—</div><div class="sub">Canary deliveries recorded in sink</div></div></div><div class="section"><h2>Conflicting source observations</h2><table class="receipts"><thead><tr><th>Flow</th><th>Reported state</th><th>Delivery ID</th></tr></thead><tbody id="c1-rows"></tbody></table></div></section>
<section class="panel" data-panel="1" hidden><div class="label">C2 · separate controlled investigation run</div><h2 class="stage-title">The agent tests, not just talks.</h2><div class="cards"><div class="card"><div class="label">Symfony AI + MCP</div><div class="metric blue">4 tools</div><div class="sub">Two observations and two canary probes, all succeeded</div></div><div class="card"><div class="label">Status-only</div><div class="metric green" id="c2-status">—</div><div class="sub">Would incorrectly close the incident</div></div><div class="card"><div class="label">Independent PHP verdict</div><div class="metric red" id="c2-verdict">—</div><div class="sub">Blocked using persisted delivery receipts</div></div></div><div class="section"><h2>Tool audit — actual requested and succeeded events</h2><div id="trace"></div></div><div class="section"><h2>Receipt IDs cited by the model and found in the sink</h2><table class="receipts"><thead><tr><th>Source</th><th>Canary</th><th>Event receipt</th></tr></thead><tbody id="c2-rows"></tbody></table></div><p class="foot">The agent is restricted to read and synthetic probe tools. No stop or approval tool was observed. The PHP verifier, not the LLM, decides the incident status.</p></section>
<section class="panel" data-panel="2" hidden><div class="label">C1 · separate approved-stop run</div><h2 class="stage-title">Now green actually means stopped.</h2><div class="cards"><div class="card"><div class="label">After one-use approved stops</div><div class="metric green" id="after-status">—</div><div class="sub">Independent verification after both flows were stopped</div></div><div class="card"><div class="label">New synthetic deliveries</div><div class="metric green" id="after-count">—</div><div class="sub">Zero in the tested observation window</div></div><div class="card"><div class="label">What this proves</div><div class="metric blue">2 flows</div><div class="sub">Known integrations only; no delayed queue guarantee</div></div></div><p class="foot"><strong>Methodological note:</strong> C1 remediation and C2 investigation are separate controlled lab runs, not a single continuous timeline. Both were validated by their respective runners.</p><div class="section"><h2>Boundaries of the claim</h2><ul id="limitations"></ul></div></section>
<div class="footer"><div><small>Local SHA-256 sidecars were checked when this evidence replay was generated. These are not signed provider attestations.</small></div><div class="controls"><button type="button" id="prev">Previous</button><button type="button" id="next">Next stage</button></div></div></main><script type="application/json" id="evidence">${embedded}</script><script>
(()=>{'use strict';const d=JSON.parse(document.getElementById('evidence').textContent);let current=0;const el=id=>document.getElementById(id);const put=(id,v)=>{el(id).textContent=String(v)};function tr(target,cells){const r=document.createElement('tr');for(const c of cells){const cell=document.createElement('td');cell.textContent=c;r.appendChild(cell)}target.appendChild(r)}
put('c1-status',d.c1.status_only);put('c1-verdict',d.c1.verdict);put('c1-count',d.c1.receipts.length);put('c2-status',d.c2.status_only);put('c2-verdict',d.c2.verdict);put('after-status',d.c1.after_stop.decision);put('after-count',d.c1.after_stop.new_deliveries);for(const x of d.c1.receipts)tr(el('c1-rows'),[x.flow,d.c1.reported_states[x.flow],x.event_id]);for(const x of d.c2.receipts)tr(el('c2-rows'),[x.flow,x.canary_id,x.event_id]);for(const name of d.c2.tool_names){const div=document.createElement('div');div.className='event';div.textContent='✓ requested → succeeded · '+name;el('trace').appendChild(div)}for(const x of d.limitations){const li=document.createElement('li');li.textContent=x;el('limitations').appendChild(li)}function show(n){current=(n+3)%3;for(const p of document.querySelectorAll('[data-panel]'))p.hidden=Number(p.dataset.panel)!==current;for(const b of document.querySelectorAll('[data-step]'))b.setAttribute('aria-pressed',String(Number(b.dataset.step)===current));el('prev').disabled=current===0;el('next').disabled=current===2}for(const b of document.querySelectorAll('[data-step]'))b.addEventListener('click',()=>show(Number(b.dataset.step)));el('prev').addEventListener('click',()=>show(current-1));el('next').addEventListener('click',()=>show(current+1));window.addEventListener('keydown',e=>{if(e.key==='ArrowRight'&&current<2)show(current+1);if(e.key==='ArrowLeft'&&current>0)show(current-1)});show(0)})();
</script></body></html>`;
  fs.writeFileSync(path.join(outDir,'stillwired-jury-demo.html'),html,'utf8');
  console.log('JURY_EVIDENCE_SHA256_PASS: 6 files');
  console.log('C1_FALSE_GREEN_PASS / C2_ACTIVE_PROBE_EVIDENCE_PASS');
  console.log('OFFLINE_HTML='+path.join(outDir,'stillwired-jury-demo.html'));
  console.log('SANITIZED_MANIFEST='+manifestPath);
  console.log('WARNING: C1 and C2 are separate controlled synthetic runs.');
}
try{run()}catch(e){console.error(e.message);console.error('JURY_DEMO_BUILD_FAIL');process.exitCode=1}
