'use strict';
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const endpoint = process.env.SW_C6_HTTP_URL || 'http://127.0.0.1:8787/webhook/stillwired_github';
const secret = process.env.SW_C6_GITHUB_WEBHOOK_SECRET;
const php = process.env.SW_PHP_BIN || 'php';
if (!secret || secret.length < 24) {
  console.error('C6_FAIL: SW_C6_GITHUB_WEBHOOK_SECRET missing or too short; configure it BEFORE starting the HTTP server.');
  process.exit(2);
}
function assert(x, msg) { if (!x) throw new Error(msg); console.log('PASS: ' + msg); }
function signature(body) { return 'sha256=' + crypto.createHmac('sha256', secret).update(body).digest('hex'); }
async function post(guid, body, sig) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 8000);
  try {
    return await fetch(endpoint, {
      method: 'POST',
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-GitHub-Event': 'push',
        'X-GitHub-Delivery': guid,
        'X-GitHub-Hook-ID': '714914745',
        ...(sig ? {'X-Hub-Signature-256': sig} : {}),
      },
      body,
    });
  } finally { clearTimeout(timeout); }
}
function audit(guid) {
  const r = spawnSync(php, ['bin/console', 'sw:c6:receipts', '--guid=' + guid, '--no-ansi'], {
    encoding: 'utf8', cwd: process.cwd(), timeout: 12000, windowsHide: true,
  });
  if (r.error || r.status !== 0) throw new Error('Audit failed: ' + (r.stderr || r.error?.message || r.status));
  return JSON.parse(r.stdout);
}
(async () => {
  console.log('C6_NATIVE_HTTP_START (synthetic signed request; not live GitHub)');
  const guid = crypto.randomUUID();
  const body = JSON.stringify({ repository: { full_name: 'guelmbaye/stillwired-webhook-lab' }, ref: 'refs/heads/master', sentinel: 'C6 synthetic signed test' });
  const bad = await post(guid, body, 'sha256=' + '0'.repeat(64));
  assert(bad.status === 401, 'bad HMAC rejected by Symfony endpoint (HTTP 401)');
  assert(audit(guid).matched_receipts.length === 0, 'invalid signature created no receipt');
  const valid = await post(guid, body, signature(body));
  assert(valid.ok, 'valid signed POST accepted by Symfony Webhook (HTTP ' + valid.status + ')');
  const replay = await post(guid, body, signature(body));
  assert(replay.ok, 'duplicate signed POST accepted idempotently (HTTP ' + replay.status + ')');
  const result = audit(guid);
  assert(result.ledger_integrity === 'HASH_CHAIN_OK', 'local hash chain validated');
  assert(result.matched_receipts.length === 1, 'exactly one receipt stored for duplicate GUID');
  assert(result.matched_receipts[0].raw_body_sha256 === crypto.createHash('sha256').update(body).digest('hex'), 'body digest preserved without raw payload');
  console.log('C6_NATIVE_HTTP_PASS GUID=' + guid);
})().catch(e => { console.error('C6_NATIVE_HTTP_FAIL: ' + e.message); process.exitCode = 1; });
