'use strict';
/** One-time narrow patch: correct only transport labels in SmokeMcpCommand.php for HTTP deployment. */
const fs=require('node:fs');const path=require('node:path');
const root=process.cwd(),file=path.join(root,'src','Command','SmokeMcpCommand.php'),cfg=path.join(root,'config','packages','mcp.yaml');
function stop(msg){console.error('METADATA_PATCH_FAIL: '+msg);process.exitCode=1}
if(!fs.existsSync(file)||!fs.existsSync(cfg))stop('Run from project root; expected Symfony files missing');
else{
 const yaml=fs.readFileSync(cfg,'utf8');
 if(!yaml.includes('127.0.0.1:8787')) stop('Current MCP configuration does not clearly identify the validated local HTTP transport');
 else{
  const src=fs.readFileSync(file,'utf8');
  const changes=[
    ['Actual STDIO MCP tools/list + tools/call smoke check, both servers','Actual HTTP MCP tools/list + tools/call smoke check, both servers'],
    ["'two independent STDIO MCP connections'","'two independent HTTP MCP connections'"],
    ["'transport' => 'stdio'","'transport' => 'http'"],
  ];
  if(changes.every(([a])=>!src.includes(a))){
    if(changes.every(([,b])=>src.includes(b)))console.log('METADATA_ALREADY_HTTP');
    else stop('Unexpected SmokeMcpCommand.php content; refusing to change');
  }else if(!changes.every(([a])=>src.includes(a))){stop('Only some expected labels present; refusing partial edit');}
  else{
    const dest=changes.reduce((s,[a,b])=>s.replace(a,b),src);
    fs.writeFileSync(file+'.before-http-metadata',src,'utf8');fs.writeFileSync(file,dest,'utf8');
    console.log('METADATA_HTTP_PATCH_PASS');
    console.log('Backup: src/Command/SmokeMcpCommand.php.before-http-metadata');
    console.log('Next: php -l src/Command/SmokeMcpCommand.php');
  }
 }
}
