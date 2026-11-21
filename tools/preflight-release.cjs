'use strict';
/** Read-only local release check. Node 18+. Does not run Composer, PHP or read .env.local values. */
const fs=require('node:fs');const path=require('node:path');const {spawnSync}=require('node:child_process');
const cwd=process.cwd();let failures=0;let warnings=0;
function check(ok,desc,level='error'){console.log((ok?'PASS':level==='error'?'FAIL':'WARN')+': '+desc);if(!ok){if(level==='error')failures++;else warnings++}}
function exists(p){return fs.existsSync(path.join(cwd,p))}
console.log('STILLWIRED_RELEASE_PREFLIGHT (read-only)');
check(exists('bin/console') && exists('src/Command/InvestigateCommand.php'),'Symfony app and AI investigation command present');
check(exists('config/packages/mcp.yaml') && exists('src/Mcp/WebhookTools.php') && exists('src/Mcp/SchedulerTools.php'),'Two MCP source implementations present');
check(exists('run-competitive-false-green.cjs') && exists('run-competitive-ai-c2.cjs'),'C1 and C2 reproducible runners present');
check(exists('composer.lock'),'composer.lock committed-ready (required for reproducible install)');
check(exists('vendor/autoload.php'),'Composer packages installed locally','warn');
if(exists('composer.json')){const c=JSON.parse(fs.readFileSync(path.join(cwd,'composer.json'),'utf8'));check(Boolean(c.require?.['nyholm/psr7']),'nyholm/psr7 explicitly required for HTTP PSR-17');check(Boolean(c.require?.['symfony/ai-agent']&&c.require?.['symfony/mcp-bundle']),'Symfony AI + MCP dependencies explicit');}else check(false,'composer.json exists');
if(exists('.gitignore')){const g=fs.readFileSync(path.join(cwd,'.gitignore'),'utf8');for(const item of ['/vendor/','/var/lab/','/var/evidence/','/.env.local'])check(g.includes(item),`.gitignore excludes ${item}`)}else check(false,'.gitignore exists');
if(exists('config/packages/mcp.yaml')){const cfg=fs.readFileSync(path.join(cwd,'config/packages/mcp.yaml'),'utf8');check(cfg.includes('127.0.0.1')&&cfg.includes('8787'),'MCP client endpoints use local loopback (127.0.0.1:8787)');check(!cfg.includes('0.0.0.0:8787'),'No public 0.0.0.0 MCP binding configured');}
check(exists('start-stillwired-http.ps1') && exists('public/router.php'),'Local HTTP development launcher and router included');
if(exists('src/Command/SmokeMcpCommand.php')){const t=fs.readFileSync(path.join(cwd,'src/Command/SmokeMcpCommand.php'),'utf8');check(t.includes("'transport' => 'http'")&&!t.includes("'transport' => 'stdio'"),'MCP smoke evidence metadata correctly reports HTTP');}
check(exists('dist/stillwired-jury-demo.html') && exists('dist/stillwired-jury-evidence.json'),'Offline demo built from actual C1 and C2 evidence');
for(const f of ['tests/run.php','tests/run-gate1-domain.php','tests/run-competitive.php','tests/run-c2-contract.php'])check(exists(f),`${f} present`);
check(exists('LICENSE'),'Open-source license included');
const git=spawnSync('git',['ls-files','-z'],{cwd,encoding:'utf8',timeout:5000,windowsHide:true});
if(git.status===0){const tracked=git.stdout.split('\0').filter(Boolean).map(x=>x.replace(/\\/g,'/'));const secret=tracked.filter(f=>f==='.env.local'||/^var\/(lab|evidence|log|cache)\//.test(f)||/^vendor\//.test(f)||/\.before-C[12]$/.test(f));check(secret.length===0,'Git index excludes lab state, secrets, vendor and backup files'+(secret.length?': '+secret.slice(0,12).join(', '):''));}else console.log('INFO: Not a Git repository yet, or Git unavailable; review git status before push.');
console.log(`RELEASE_PREFLIGHT_${failures===0?'PASS':'FAIL'}: ${failures} blocking, ${warnings} warnings`);process.exitCode=failures?1:0;
