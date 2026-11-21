'use strict';
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const project = process.cwd();
const php = process.env.SW_PHP_BIN || 'php';
const evidenceDir = path.join(project, 'var', 'evidence');
const timeoutMs = 15000;

function command(step, ...args) {
    const r = spawnSync(php, ['bin/console', 'sw:lab', ...args], {
        cwd: project, encoding: 'utf8', timeout: timeoutMs,
        maxBuffer: 1048576, windowsHide: true
    });
    if (r.error || r.status !== 0) {
        throw new Error(`STEP ${step} failed (exit=${r.status}): ${r.error?.message || ''}\n${r.stdout || ''}\n${r.stderr || ''}`);
    }
    console.log(`STEP ${step}: PASS`);
    return (r.stdout || '').trim();
}
function insist(ok, reason) {
    if (!ok) throw new Error(`ASSERTION_FAILED: ${reason}`);
}
function verify(stage, expectedDecision, expectedDeliveries) {
    const output = command(`verify-${stage}`, 'verify');
    const cut = output.indexOf('\nEVIDENCE:');
    insist(cut > 0, 'Missing EVIDENCE marker in verify output');
    const report = JSON.parse(output.slice(0, cut));
    insist(report.decision === expectedDecision, `${stage}: expected ${expectedDecision}, got ${report.decision}`);
    insist(report.deliveries.length === expectedDeliveries, `${stage}: expected ${expectedDeliveries} deliveries, got ${report.deliveries.length}`);
    const original = path.join(evidenceDir, `gate1-${report.decision.toLowerCase()}.json`);
    const stored = fs.readFileSync(original);
    const claimedSha = fs.readFileSync(original + '.sha256', 'utf8').trim().split(/\s+/)[0].toLowerCase();
    const actualSha = crypto.createHash('sha256').update(stored).digest('hex');
    insist(claimedSha === actualSha, `${stage}: evidence SHA-256 mismatch`);
    const destination = path.join(evidenceDir, `competitive-${stage}.json`);
    fs.copyFileSync(original, destination);
    fs.copyFileSync(original + '.sha256', destination + '.sha256');
    return report;
}
function showcase(exposed, closed) {
    const esc = (x) => String(x).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
    const names = {webhook: 'Webhook', scheduled_export: 'Scheduled export'};
    const rows = Object.entries(exposed.observed_states).map(([id, state]) => {
        const delivered = exposed.deliveries.filter((d) => d.flow === id);
        return `<tr><td>${esc(names[id] || id)}</td><td><span class="pill neutral">${esc(state)}</span></td><td><span class="pill bad">${delivered.length} delivered</span></td><td class="mono">${esc(delivered[0]?.event_id || 'none')}</td></tr>`;
    }).join('');
    const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>STILLWIRED — False Green Evidence</title><style>
:root{color-scheme:dark;font:16px/1.55 system-ui,Segoe UI,sans-serif;background:#090e19;color:#edf2ff}*{box-sizing:border-box}body{margin:0}.wrap{max-width:1120px;margin:0 auto;padding:48px 24px 70px}.eyebrow{letter-spacing:.16em;text-transform:uppercase;font-size:12px;color:#97a6c6;font-weight:700}.brand{font-weight:900;letter-spacing:.08em;color:#a5b8ff}.lead{font-size:clamp(34px,5vw,66px);line-height:1.08;letter-spacing:-.05em;max-width:940px;margin:16px 0}p{color:#b6c4dd}h2{font-size:19px;margin:0 0 10px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:38px 0 20px}.panel{border:1px solid #27334d;background:#131d30;border-radius:20px;padding:24px;min-height:235px}.flag{font-size:12px;font-weight:800;letter-spacing:.12em}.bad{color:#ff8f99}.good{color:#74e9af}.metric{font-size:38px;font-weight:900;line-height:1.1;margin:14px 0}.danger{background:#271a2b;border-color:#9f415b}.ok{background:#122a29;border-color:#286f5b}.pill{padding:4px 9px;border-radius:999px;font-weight:700;font-size:12px}.pill.neutral{background:#313d50;color:#d1ddf1}.pill.bad{background:#5b2837;color:#ffb5c2}table{width:100%;border-collapse:collapse;background:#131d30;border-radius:16px;overflow:hidden}td,th{text-align:left;padding:14px;border-bottom:1px solid #26334b}th{color:#a3b2cb;text-transform:uppercase;letter-spacing:.07em;font-size:12px}.mono{font-family:Consolas,monospace;font-size:12px}.foot{margin-top:22px;font-size:13px;color:#93a5c4}code{color:#c6d6ff}details{margin-top:26px;background:#101a2d;border:1px solid #27334d;border-radius:12px;padding:14px;overflow:auto}pre{white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px}@media(max-width:820px){.grid{grid-template-columns:1fr}.wrap{padding-top:25px}}
</style></head><body><main class="wrap"><div class="eyebrow"><span class="brand">STILLWIRED</span> / COMPETITIVE PROOF C1</div><h1 class="lead">The dashboard is green.<br>The data is still leaving.</h1><p>After the human account is revoked, both integration providers report <strong>disabled</strong>. An independent canary delivered through the real Symfony → MCP lab still reaches the sink.</p><section class="grid"><article class="panel"><div class="flag">01 / STATUS-ONLY BASELINE</div><div class="metric good">${esc(exposed.status_only_baseline)}</div><p>Both providers say disabled. A control-plane-only checker would close the incident.</p></article><article class="panel danger"><div class="flag bad">02 / INDEPENDENT CANARY TEST</div><div class="metric bad">${esc(exposed.decision)}</div><p><strong>${exposed.deliveries.length} delivered canaries</strong> contradict source metadata. Closure is blocked.</p></article><article class="panel ok"><div class="flag good">03 / AFTER APPROVED STOPS</div><div class="metric good">${esc(closed.decision)}</div><p><strong>${closed.deliveries.length} new deliveries</strong> after both one-use approved revocations.</p></article></section><h2>Conflicting observations</h2><table><thead><tr><th>Independent flow</th><th>Reported state</th><th>Canary sink</th><th>Event receipt</th></tr></thead><tbody>${rows}</tbody></table><p class="foot">This report reflects a synthetic fault-injected demonstration involving only the enumerated webhook and scheduled export. It does not establish delayed-queue safety, discover unknown integrations, or assert that real vendors misreport status. Evidence JSON and SHA-256 files accompany this report.</p><details><summary>Source-verifiable evidence (JSON)</summary><pre>${esc(JSON.stringify({false_green:exposed,after_remediation:closed},null,2))}</pre></details></main></body></html>`;
    const file = path.join(evidenceDir, 'competitive-showcase.html');
    fs.writeFileSync(file, html, 'utf8');
    console.log('SHOWCASE_HTML=' + file);
}
function stop(flow) {
    const approval = command(`approve-${flow}`, 'approve', flow);
    const token = approval.split(': ').pop().trim();
    insist(/^[A-Za-z0-9_-]+$/.test(token), `Invalid approval token received for ${flow}`);
    const stopped = command(`stop-${flow}`, 'stop', flow, token);
    insist(stopped.includes('"changed":true') || stopped.includes('"changed": true'), `Source did not confirm first stop of ${flow}`);
}
try {
    console.log('STILLWIRED_COMPETITIVE_FALSE_GREEN');
    console.log('WARNING: Resets ONLY var/lab synthetic state. Start local HTTP MCP server first.');
    command('reset', 'reset');
    command('revoke-human', 'revoke');
    command('stale-webhook', 'fault', 'webhook', 'stale');
    command('stale-scheduled_export', 'fault', 'scheduled_export', 'stale');
    const exposed = verify('false-green', 'EXPOSED', 2);
    insist(exposed.account_active === false, 'Human was not revoked');
    insist(exposed.observed_states.webhook === 'disabled' && exposed.observed_states.scheduled_export === 'disabled', 'State-only dashboard must see both disabled');
    insist(exposed.status_only_baseline === 'VERIFIED', 'Status-only baseline must be falsely green');
    insist(exposed.status_delivery_conflicts.length === 2, 'Both sources must have contradictory delivery evidence');
    console.log('WINNING_MOMENT: STATUS_ONLY=VERIFIED BUT CANARY_VERDICT=EXPOSED');
    console.log('CONTRADICTED_SOURCES=' + exposed.status_delivery_conflicts.join(','));
    stop('webhook');
    stop('scheduled_export');
    const closed = verify('truly-closed', 'VERIFIED', 0);
    insist(closed.status_delivery_conflicts.length === 0, 'Unexpected contradictory delivery after stops');
    insist(closed.observed_states.webhook === 'disabled' && closed.observed_states.scheduled_export === 'disabled', 'Sources not reported disabled');
    console.log('AFTER_APPROVED_STOP: STATUS_ONLY=VERIFIED CANARY_VERDICT=VERIFIED');
    showcase(exposed, closed);
    console.log('COMPETITIVE_FALSE_GREEN_PASS');
} catch (err) {
    console.error(err.stack || err.message);
    console.error('COMPETITIVE_FALSE_GREEN_FAIL');
    process.exitCode = 1;
}
