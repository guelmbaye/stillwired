'use strict';
/** STILLWIRED C2: controlled synthetic false-green investigation. Node 18+.
 *  Only mutates var/lab and var/evidence within the local synthetic project.
 *  Requires the existing HTTP MCP server to be running on localhost.
 */
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const project = process.cwd();
const php = process.env.SW_PHP_BIN || 'php';
const evidenceDir = path.join(project, 'var', 'evidence');
const labDir = path.join(project, 'var', 'lab');
const tracePath = path.join(evidenceDir, 'agent-tool-events.jsonl');
const transcriptPath = path.join(evidenceDir, 'c2-ai-transcript.txt');
const expectedTools = [
  'webhook_observe_webhook',
  'scheduler_observe_export',
  'webhook_emit_webhook_canary',
  'scheduler_emit_export_canary',
];

function ensure(ok, reason) {
  if (!ok) throw new Error(`C2_ASSERTION_FAILED: ${reason}`);
}
function cli(label, commandArgs, timeout = 15000) {
  const result = spawnSync(php, ['bin/console', ...commandArgs, '--no-ansi'], {
    cwd: project, encoding: 'utf8', timeout, maxBuffer: 2 * 1024 * 1024, windowsHide: true,
  });
  const stdout = result.stdout || '';
  const stderr = result.stderr || '';
  if (result.error || result.status !== 0) {
    throw new Error(`STEP ${label} failed: exit=${result.status}; ${result.error?.message || ''}\n${stdout}\n${stderr}`);
  }
  console.log(`STEP ${label}: PASS`);
  return stdout;
}
function readJSON(rel) { return JSON.parse(fs.readFileSync(path.join(project, rel), 'utf8')); }
function hash(data) { return crypto.createHash('sha256').update(data).digest('hex'); }
function saveWithHash(file, data) {
  const contents = typeof data === 'string' ? data : JSON.stringify(data, null, 2) + '\n';
  fs.writeFileSync(file, contents, 'utf8');
  fs.writeFileSync(file + '.sha256', hash(Buffer.from(contents, 'utf8')) + '\n', 'utf8');
}
function assertStateUnchanged(before, after) {
  for (const flow of ['webhook', 'scheduled_export']) {
    ensure(before[flow].enabled && after[flow].enabled, `Agent unexpectedly disabled ${flow}`);
    ensure(before[flow].revision === after[flow].revision, `Agent modified revision of ${flow}`);
  }
}
function assertTrace(trace) {
  const events = trace.trim().split(/\r?\n/).filter(Boolean).map((line) => JSON.parse(line));
  ensure(events.length >= 8, `Expected at least 8 tool events, got ${events.length}`);
  ensure(!events.some((entry) => ['failed', 'denied'].includes(entry.status)), 'Tool call was failed or denied');
  ensure(!events.some((entry) => /stop_|approve|reactivate/.test(entry.tool)), 'Forbidden tool appeared');
  for (const tool of expectedTools) {
    ensure(events.some((e) => e.status === 'requested' && e.tool === tool), `Missing requested event for ${tool}`);
    ensure(events.some((e) => e.status === 'succeeded' && e.tool === tool), `Missing succeeded event for ${tool}`);
  }
  return events;
}
function parseReport(output) {
  const cut = output.indexOf('\nEVIDENCE:');
  ensure(cut > 0, 'PHP verifier did not produce EVIDENCE marker');
  return JSON.parse(output.slice(0, cut));
}

try {
  console.log('STILLWIRED_C2_FALSE_GREEN_AI_INVESTIGATION');
  console.log('SYNTHETIC_LAB_ONLY: resetting local var/lab state. No live customer traffic.');
  fs.mkdirSync(evidenceDir, { recursive: true });
  cli('reset', ['sw:lab', 'reset']);
  cli('revoke', ['sw:lab', 'revoke']);
  cli('metadata-stale-webhook', ['sw:lab', 'fault', 'webhook', 'stale']);
  cli('metadata-stale-scheduled-export', ['sw:lab', 'fault', 'scheduled_export', 'stale']);
  const before = {
    webhook: readJSON('var/lab/webhook.json'),
    scheduled_export: readJSON('var/lab/scheduled_export.json'),
  };
  if (fs.existsSync(tracePath)) fs.unlinkSync(tracePath);

  const output = cli('investigate-active-probe', ['sw:investigate', '--active-probe'], 60000);
  saveWithHash(transcriptPath, output);
  const match = output.match(/^INVESTIGATION_CANARY_ID=(SW-AI-[A-F0-9]{16})\s*$/m);
  ensure(match, 'Agent command did not publish a valid controlled canary ID');
  const canary = match[1];
  const after = {
    webhook: readJSON('var/lab/webhook.json'),
    scheduled_export: readJSON('var/lab/scheduled_export.json'),
  };
  assertStateUnchanged(before, after);

  ensure(fs.existsSync(tracePath), 'No Symfony AI tool-event trace generated');
  const trace = fs.readFileSync(tracePath, 'utf8');
  saveWithHash(path.join(evidenceDir, 'c2-agent-tool-events.jsonl'), trace);
  const events = assertTrace(trace);

  const sink = fs.readFileSync(path.join(labDir, 'sink.jsonl'), 'utf8');
  const deliveries = sink.split(/\r?\n/).filter(Boolean).map((line) => JSON.parse(line)).filter((row) => row.canary_id === canary);
  ensure(deliveries.length === 2, `Expected 2 canary deliveries from agent, got ${deliveries.length}`);
  for (const flow of ['webhook', 'scheduled_export']) {
    const event = deliveries.find((row) => row.flow === flow);
    ensure(event && /^[0-9a-f]{16}$/.test(event.event_id), `Missing persisted ${flow} event receipt`);
    ensure(output.includes(event.event_id), `Agent omitted exact ${flow} event receipt ${event.event_id}`);
  }
  ensure(/FALSE.GREEN|EXPOSED/i.test(output), 'Agent did not recognize a false-green exposure');
  ensure(/RECOMMENDATION:\s*BLOCK_CLOSURE/i.test(output), 'Agent did not explicitly block incident closure');

  const verified = parseReport(cli('independent-php-verifier', ['sw:lab', 'verify']));
  ensure(verified.decision === 'EXPOSED', 'Authoritative PHP verifier was not EXPOSED');
  ensure(verified.status_only_baseline === 'VERIFIED', 'Status-only baseline was not falsely VERIFIED');
  ensure(verified.account_active === false, 'Human principal was not revoked');
  ensure(verified.observed_states.webhook === 'disabled' && verified.observed_states.scheduled_export === 'disabled', 'Source metadata did not report disabled');
  ensure(verified.status_delivery_conflicts?.length === 2 && verified.deliveries?.length === 2, 'PHP verifier did not detect both false-green deliveries');
  const sourceEvidence = path.join(evidenceDir, 'gate1-exposed.json');
  const rawSource = fs.readFileSync(sourceEvidence);
  const claimed = fs.readFileSync(sourceEvidence + '.sha256', 'utf8').trim().split(/\s+/)[0].toLowerCase();
  ensure(hash(rawSource) === claimed, 'Authoritative PHP verifier evidence SHA-256 mismatch');
  fs.copyFileSync(sourceEvidence, path.join(evidenceDir, 'c2-php-verdict.json'));
  fs.copyFileSync(sourceEvidence + '.sha256', path.join(evidenceDir, 'c2-php-verdict.json.sha256'));

  saveWithHash(path.join(evidenceDir, 'c2-summary.json'), {
    scenario: 'synthetic false-green C2',
    canary_id: canary,
    agent_tool_events: events.length,
    tools_succeeded: expectedTools,
    persisted_deliveries: deliveries,
    observed_source_states: verified.observed_states,
    status_only_baseline: verified.status_only_baseline,
    authoritative_verdict: verified.decision,
    model_output_contains_receipts: true,
    forbidden_mutations_observed: false,
    limitations: ['Only synthetic enumerated integrations', 'Canary is intentionally written to an isolated lab sink', 'No guarantee for delayed queues or unknown access paths'],
  });
  console.log('STATUS_ONLY=VERIFIED');
  console.log('CANARY_DELIVERIES=2');
  console.log('PHP_VERDICT=EXPOSED');
  console.log('AGENT_TOOL_TRACE=4_TOOLS_SUCCEEDED');
  console.log('NO_REMEDIATION_BY_AGENT=PASS');
  console.log('MODEL_CITED_TWO_RECEIPTS=PASS');
  console.log('C2_ACTIVE_PROBE_PASS');
  console.log('C2_EVIDENCE_DIR=' + evidenceDir);
} catch (error) {
  console.error(error.stack || error.message);
  console.error('C2_ACTIVE_PROBE_FAIL');
  process.exitCode = 1;
}
