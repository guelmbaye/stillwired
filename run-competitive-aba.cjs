'use strict';
/** STILLWIRED C3: A-B-A evidence challenge (REVOKELINE method).
 * Same incident and *reported disabled states* in all three worlds.
 * Hidden source-owned enabled state changes only between test worlds.
 * The agent never receives the world label or hidden state as input.
 * Executes ONLY against the local synthetic Symfony MCP lab.
 * All CLI subprocesses are bounded; never logs approval tokens.
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');

const root = process.cwd();
const php = process.env.SW_PHP_BIN || 'php';
const evDir = path.join(root, 'var', 'evidence');
const labDir = path.join(root, 'var', 'lab');
const tracePath = path.join(evDir, 'agent-tool-events.jsonl');
const flows = ['webhook', 'scheduled_export'];
const tools = [
  'webhook_observe_webhook', 'scheduler_observe_export',
  'webhook_emit_webhook_canary', 'scheduler_emit_export_canary',
];
const worlds = [];
const insist = (test, why) => { if (!test) throw new Error('C3_ASSERTION_FAILED: ' + why); };
const digest = (buffer) => crypto.createHash('sha256').update(buffer).digest('hex');
const save = (name, content) => {
  const file = path.join(evDir, name);
  const data = typeof content === 'string' ? content : JSON.stringify(content, null, 2) + '\n';
  fs.writeFileSync(file, data, 'utf8');
  fs.writeFileSync(file + '.sha256', digest(Buffer.from(data, 'utf8')) + '\n', 'utf8');
};
function cli(label, args, timeoutMs = 15000) {
  const r = spawnSync(php, ['bin/console', ...args, '--no-ansi'], {
    cwd: root, encoding: 'utf8', timeout: timeoutMs,
    windowsHide: true, maxBuffer: 2 * 1024 * 1024,
  });
  if (r.error || r.status !== 0) {
    // Approval tokens and user secrets are never included in errors.
    throw new Error(`STEP ${label}: FAILED exit=${r.status}; ${r.error?.message || ''}; ${
      args[0] === 'sw:lab' && args[1] === 'approve' ? 'approval command failed' : (r.stderr || r.stdout || '').slice(0, 800)
    }`);
  }
  console.log('STEP ' + label + ': PASS');
  return r.stdout || '';
}
function state() {
  return Object.fromEntries(flows.map(f => [f, JSON.parse(fs.readFileSync(path.join(labDir, f + '.json'), 'utf8'))]));
}
function stop(flow) {
  const issued = cli(`approve-${flow}`, ['sw:lab', 'approve', flow]);
  const match = issued.match(/:\s*([a-zA-Z0-9_-]+)\s*$/);
  insist(match, `Cannot parse approval token for ${flow}`);
  const r = cli(`stop-${flow}`, ['sw:lab', 'stop', flow, match[1]]);
  insist(/"changed"\s*:\s*true/.test(r), `Expected real state transition when stopping ${flow}`);
}
function parseVerifierOutput(text) {
  const marker = text.indexOf('\nEVIDENCE:');
  insist(marker > 0, 'PHP verifier evidence marker missing');
  return JSON.parse(text.slice(0, marker));
}
function checkTrace(raw) {
  const events = raw.trim().split(/\r?\n/).filter(Boolean).map(x => JSON.parse(x));
  for (const tool of tools) {
    insist(events.some(e => e.tool === tool && e.status === 'requested'), 'Missing requested ' + tool);
    insist(events.some(e => e.tool === tool && e.status === 'succeeded'), 'Missing succeeded ' + tool);
  }
  insist(!events.some(e => /stop_|approve|reactivate/.test(e.tool || '')), 'Agent attempted forbidden action');
  insist(!events.some(e => ['denied', 'failed'].includes(e.status)), 'Agent had denied/failed tool');
  return events;
}
function stage(label, truthEnabled, expectedVerdict, expectedCount, expectedRecommendation) {
  console.log('\n== WORLD ' + label + ' ==');
  const before = state();
  insist(flows.every(f => before[f].enabled === truthEnabled), 'Wrong hidden truth in ' + label);
  fs.rmSync(tracePath, { force: true });

  // NOTE: Identical tool permissions, incident text, instructions and tool names
  // across A, B, A'. World labels, fixture switches and hidden state are NOT
  // passed as model inputs. Source revision metadata can differ across worlds.
  const agentText = cli(`agent-${label}`, ['sw:investigate', '--active-probe'], 60000);
  const canaryMatch = agentText.match(/^INVESTIGATION_CANARY_ID=(SW-AI-[A-F0-9]{16})\s*$/m);
  insist(canaryMatch, 'Agent failed to issue controlled canary ID in ' + label);
  const canary = canaryMatch[1];
  const after = state();
  for (const flow of flows) {
    insist(before[flow].enabled === after[flow].enabled && before[flow].revision === after[flow].revision,
      `Agent modified ${flow} state in ${label}`);
  }
  const trace = fs.readFileSync(tracePath, 'utf8');
  const events = checkTrace(trace);
  const rows = fs.readFileSync(path.join(labDir, 'sink.jsonl'), 'utf8').split(/\r?\n/)
    .filter(Boolean).map(s => JSON.parse(s)).filter(e => e.canary_id === canary);
  insist(rows.length === expectedCount, `Agent delivered ${rows.length}, wanted ${expectedCount} in ${label}`);
  insist(new Set(rows.map(r => r.flow)).size === rows.length, `Duplicate flow receipts in ${label}`);
  for (const row of rows) {
    insist(typeof row.event_id === 'string' && /^[0-9a-f]{16}$/.test(row.event_id), 'Invalid event ID');
    insist(agentText.includes(row.event_id), `Model did not cite ${row.event_id} in ${label}`);
  }
  insist(new RegExp('RECOMMENDATION:\\s*' + expectedRecommendation).test(agentText),
    `Model recommendation mismatch in ${label}: expected ${expectedRecommendation}`);

  const verifierText = cli(`php-verify-${label}`, ['sw:lab', 'verify']);
  const verified = parseVerifierOutput(verifierText);
  insist(verified.decision === expectedVerdict, `PHP decision incorrect in ${label}`);
  insist(verified.status_only_baseline === 'VERIFIED', `Status-only dashboard changed in ${label}`);
  insist(verified.account_active === false, 'Revoked contractor must remain revoked');
  insist(flows.every(f => verified.observed_states[f] === 'disabled'), `Source stated status changed in ${label}`);
  insist(verified.deliveries.length === expectedCount, `PHP delivery count incorrect in ${label}`);
  insist(verified.status_delivery_conflicts.length === expectedCount, `Contradiction count incorrect in ${label}`);
  insist(rows.every(r => !verified.deliveries.some(v => v.canary_id === r.canary_id)),
    'Verifier must use its own new canary ID');

  const source = path.join(evDir, 'gate1-' + verified.decision.toLowerCase() + '.json');
  const sourceBytes = fs.readFileSync(source);
  const sourceSha = fs.readFileSync(source + '.sha256', 'utf8').trim().split(/\s+/)[0].toLowerCase();
  insist(digest(sourceBytes) === sourceSha, `PHP evidence hash mismatch in ${label}`);

  save(`c3-${label}-agent.txt`, agentText);
  save(`c3-${label}-trace.jsonl`, trace);
  save(`c3-${label}-php-verdict.json`, sourceBytes.toString('utf8'));
  const row = {
    world: label,
    reported_state: 'disabled',
    hidden_actual_state: truthEnabled ? 'active' : 'disabled',
    source_revision_note: 'Revisions may differ across worlds',
    status_only: verified.status_only_baseline,
    agent_recommendation: expectedRecommendation,
    authoritative_verdict: verified.decision,
    agent_canary_deliveries: rows.length,
    independent_verifier_canary_deliveries: verified.deliveries.length,
    agent_tools_requested_and_succeeded: tools,
    local_evidence_hashes_valid: true,
  };
  worlds.push(row);
  console.log(`WORLD_${label}: STATUS_ONLY=${row.status_only} ACTUAL=${row.hidden_actual_state} ` +
    `AGENT=${row.agent_recommendation} PHP=${row.authoritative_verdict} RECEIPTS=${row.agent_canary_deliveries}`);
}
try {
  insist(fs.existsSync(path.join(root, 'bin', 'console')), 'Run from STILLWIRED Symfony project root');
  fs.mkdirSync(evDir, { recursive: true });
  console.log('STILLWIRED_C3_REVOKELINE_METHOD_A_B_A');
  console.log('WARNING: Resets ONLY the synthetic var/lab state. Requires local HTTP MCP server and an LLM key.');
  console.log('A-B-A worlds are set by the harness outside model messages; a source revision may reveal a transition, but not actual delivery.');
  cli('reset', ['sw:lab', 'reset']);
  cli('revoke-human', ['sw:lab', 'revoke']);
  // Freeze reported status = disabled across worlds using the existing fault flag.
  for (const flow of flows) cli(`stale-${flow}`, ['sw:lab', 'fault', flow, 'stale']);

  // A: both actually stopped; no synthetic canary should reach the sink.
  for (const flow of flows) stop(flow);
  stage('A', false, 'VERIFIED', 0, 'REQUIRE_PHP_VERIFIER');

  // B: both were reactivated behind unchanged 'disabled' metadata.
  for (const flow of flows) cli(`reactivate-${flow}`, ['sw:lab', 'reactivate', flow]);
  stage('B', true, 'EXPOSED', 2, 'BLOCK_CLOSURE');

  // A': re-stop both; status-only still reports disabled, but actual canaries cease.
  for (const flow of flows) stop(flow);
  stage('A2', false, 'VERIFIED', 0, 'REQUIRE_PHP_VERIFIER');

  insist(worlds.map(w=>w.status_only).join(',') === 'VERIFIED,VERIFIED,VERIFIED', 'Baseline not frozen');
  insist(worlds.map(w=>w.authoritative_verdict).join(',') === 'VERIFIED,EXPOSED,VERIFIED', 'No causal A-B-A verdict flip');
  const summary = {
    method: 'REVOKELINE evidence-first A-B-A challenge',
    artifact: 'STILLWIRED C3 synthetic integration benchmark',
    no_hidden_truth_passed_to_agent: true,
    identical_reported_states: true,
    important_qualification: 'Reported revisions can vary; only disabled state is held identical. These are synthetic cases.',
    worlds,
    exposed_worlds: 1,
    false_safe_status_only: 1,
    false_safe_php: 0,
    unsigned_local_sha256_only: true,
    limitations: ['Only two known integrations','No delayed-queue guarantee','No independent signed provider attestation','LLM prompting includes generic conditional contradiction guidance'],
  };
  save('c3-aba-summary.json', summary);
  console.log('BASELINE_VERDICTS=VERIFIED,VERIFIED,VERIFIED');
  console.log('PHP_VERDICTS=VERIFIED,EXPOSED,VERIFIED');
  console.log('FALSE_SAFE_ON_EXPOSED_WORLDS=status_only:1/1,php:0/1');
  console.log('C3_REVOKELINE_ABA_PASS');
} catch (e) {
  console.error(String(e.stack || e));
  console.error('C3_REVOKELINE_ABA_FAIL');
  process.exitCode = 1;
}
