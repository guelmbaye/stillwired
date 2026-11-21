#!/usr/bin/env node
/**
 * STILLWIRED Gate 1 synthetic end-to-end proof runner.
 * Requires an already-running loopback Symfony MCP HTTP server.
 * Runs ONLY against the local synthetic lab; it resets var/lab state.
 * Usage: node .\stillwired-gate1-e2e.mjs [--negative]
 * Default per-command timeout: 15 seconds (override SW_COMMAND_TIMEOUT_MS).
 */
import { spawnSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join, isAbsolute, resolve } from 'node:path';

const root = process.cwd();
const php = process.env.SW_PHP_BIN || (process.platform === 'win32' ? 'C:\\xampp\\php\\php.exe' : 'php');
const timeout = Number(process.env.SW_COMMAND_TIMEOUT_MS || 15000);
const negative = process.argv.includes('--negative');
if (!Number.isSafeInteger(timeout) || timeout < 2000 || timeout > 120000) {
  throw new Error('SW_COMMAND_TIMEOUT_MS must be between 2000 and 120000');
}
if (!existsSync(join(root, 'bin', 'console'))) throw new Error('Run from the STILLWIRED project root.');

function die(message) { throw new Error(message); }
function check(condition, message) { if (!condition) die(message); }
function command(label, args, { allowNonzero = false } = {}) {
  const result = spawnSync(php, ['bin/console', ...args, '--no-ansi'], {
    cwd: root, shell: false, windowsHide: true, encoding: 'utf8', timeout,
    maxBuffer: 2 * 1024 * 1024,
  });
  const stdout = (result.stdout || '').trim();
  const stderr = (result.stderr || '').trim();
  if (result.error) die(`${label}: ${result.error.message}`);
  if (result.signal) die(`${label}: terminated by signal ${result.signal}`);
  if (result.status !== 0 && !allowNonzero) {
    die(`${label}: exit=${result.status} ${[stdout, stderr].filter(Boolean).join(' | ').slice(0, 800)}`);
  }
  if (typeof result.status !== 'number') die(`${label}: missing exit code`);
  console.log(`STEP ${label}: exit=${result.status}`);
  return { stdout, stderr, status: result.status };
}
function requireOutput(label, args, expected) {
  const r = command(label, args);
  check(r.stdout.includes(expected), `${label}: missing ${JSON.stringify(expected)}; got ${r.stdout.slice(0, 200)}`);
  return r;
}
function integrity(path) {
  const full = isAbsolute(path) ? path : resolve(root, path);
  const bytes = readFileSync(full);
  const expected = readFileSync(full + '.sha256', 'utf8').trim();
  const got = createHash('sha256').update(bytes).digest('hex');
  check(expected === got, `Evidence checksum mismatch: ${full}`);
}
function verify(expected, deliveries, states) {
  const r = command(`verify-${expected}`, ['sw:lab', 'verify'], { allowNonzero: expected === 'INCONCLUSIVE' });
  const marker = /(?:^|\r?\n)EVIDENCE:\s*(.+)\s*$/m.exec(r.stdout);
  check(Boolean(marker), `verify-${expected}: missing EVIDENCE marker`);
  const json = r.stdout.slice(0, marker.index).trim();
  let report;
  try { report = JSON.parse(json); } catch (e) { die(`verify-${expected}: invalid JSON report: ${e.message}`); }
  check(report.decision === expected, `expected decision ${expected}, got ${report.decision}`);
  check(report.account_active === false, 'The human account must remain revoked');
  check(Array.isArray(report.deliveries) && report.deliveries.length === deliveries,
    `${expected}: expected ${deliveries} deliveries, got ${report.deliveries?.length}`);
  for (const [flow, expectedState] of Object.entries(states)) {
    check(report.observed_states?.[flow] === expectedState,
      `${expected}: ${flow} expected ${expectedState}, got ${report.observed_states?.[flow]}`);
  }
  if (expected === 'INCONCLUSIVE') check(r.status !== 0, 'INCONCLUSIVE should exit non-zero');
  else check(r.status === 0, `${expected} should exit zero`);
  integrity(marker[1].trim());
  console.log(`PASS VERDICT=${expected} DELIVERY_COUNT=${deliveries} EVIDENCE_SHA256_OK`);
  return report;
}
function approval(flow) {
  const r = command(`approve-${flow}`, ['sw:lab', 'approve', flow]);
  const m = /APPROVAL_TOKEN[^\r\n]*:\s*([a-f0-9]{48})\s*$/i.exec(r.stdout);
  check(Boolean(m), `approve-${flow}: token not found`);
  return m[1];
}
function stop(flow) {
  const token = approval(flow);
  // Tokens are intentionally never printed or stored by this runner.
  const r = command(`stop-${flow}`, ['sw:lab', 'stop', flow, token]);
  let result;
  try { result = JSON.parse(r.stdout); } catch { die(`stop-${flow}: expected JSON, got ${r.stdout.slice(0, 200)}`); }
  check(result.flow === flow && result.changed === true,
    `stop-${flow}: expected changed=true, got ${JSON.stringify(result)}`);
  console.log(`PASS REVOKED_FLOW=${flow}`);
}

try {
  console.log(`PROJECT=${root}`);
  console.log(`PHP=${php} TIMEOUT_PER_COMMAND=${timeout}ms`);
  console.log('WARNING: this run resets ONLY the synthetic STILLWIRED var/lab state.');
  requireOutput('reset', ['sw:lab', 'reset'], 'LAB_RESET');
  requireOutput('revoke-human', ['sw:lab', 'revoke'], 'HUMAN_REVOKED');
  verify('EXPOSED', 2, { webhook: 'active', scheduled_export: 'active' });
  stop('webhook');
  stop('scheduled_export');
  verify('VERIFIED', 0, { webhook: 'disabled', scheduled_export: 'disabled' });

  if (negative) {
    requireOutput('inject-webhook-fault', ['sw:lab', 'fault', 'webhook', 'down'], 'FAULT_INJECTED');
    try {
      verify('INCONCLUSIVE', 0, { webhook: 'unknown', scheduled_export: 'disabled' });
    } finally {
      requireOutput('clear-webhook-fault', ['sw:lab', 'fault', 'webhook', 'up'], 'FAULT_INJECTED');
    }
    requireOutput('reactivate-export', ['sw:lab', 'reactivate', 'scheduled_export'], 'REACTIVATED');
    verify('EXPOSED', 1, { webhook: 'disabled', scheduled_export: 'active' });
    stop('scheduled_export');
    verify('VERIFIED', 0, { webhook: 'disabled', scheduled_export: 'disabled' });
  }
  console.log(negative ? 'GATE1_E2E_NEGATIVE_PASS' : 'GATE1_E2E_PASS');
} catch (error) {
  console.error(`GATE1_E2E_FAIL: ${error.message}`);
  process.exitCode = 1;
}
