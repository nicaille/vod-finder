const {mkdtempSync,cpSync,mkdirSync,symlinkSync,writeFileSync,rmSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join,resolve} = require('node:path');
const {randomBytes} = require('node:crypto');
const {spawn,spawnSync} = require('node:child_process');
(async () => {
  const repo = resolve(__dirname,'../..');
  const root = mkdtempSync(join(tmpdir(),'vod-browser-'));
  const port = process.env.VOD_BROWSER_PORT || '18765';
  const env = {...process.env,VOD_BROWSER_ROOT:root,VOD_BROWSER_PORT:port};
  const php = process.env.VOD_TEST_PHP || 'php';
  let server;
  try {
    for (const dir of ['app','bootstrap','config','database','routes','resources','public']) cpSync(join(repo,dir),join(root,dir),{recursive:true,filter:source=>!source.includes('/bootstrap/cache/') && !source.includes('/public/hot') && !source.includes('/public/storage') && !source.endsWith('.sqlite')});
    for (const file of ['artisan','composer.json']) cpSync(join(repo,file),join(root,file));
    symlinkSync(join(repo,'vendor'),join(root,'vendor'),'dir');
    for (const dir of ['storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache']) mkdirSync(join(root,dir),{recursive:true});
    writeFileSync(join(root,'test.sqlite'),'');
    writeFileSync(join(root,'.env'),`APP_NAME="VOD Finder"\nAPP_ENV=production\nAPP_DEBUG=false\nAPP_URL=http://127.0.0.1:${port}\nAPP_KEY=base64:${randomBytes(32).toString('base64')}\nDB_CONNECTION=sqlite\nDB_DATABASE=${root}/test.sqlite\nDATABASE_URL=\nCACHE_DRIVER=file\nSESSION_DRIVER=file\nSESSION_SECURE_COOKIE=false\nMAIL_MAILER=array\nQUEUE_CONNECTION=sync\nBCRYPT_ROUNDS=4\nLOG_CHANNEL=single\n`);
    // Clear inherited DB/app overrides so the disposable .env is authoritative.
    for (const key of Object.keys(env)) {
      if (/^(APP_|DB_|DATABASE_URL$|CACHE_|SESSION_|MAIL_|QUEUE_)/.test(key)) delete env[key];
    }
    const seeded = spawnSync(php,[join(__dirname,'seed.php')],{cwd:root,env,stdio:'inherit'});
    if (seeded.status !== 0) throw new Error('Browser test database initialization failed');
    server = spawn(php,['-S',`127.0.0.1:${port}`,'-t',join(root,'public'),join(__dirname,'server.php')],{cwd:root,env,stdio:['ignore','ignore','pipe']});
    let serverLog='';server.stderr.on('data',chunk=>serverLog+=chunk);
    let ready=false;let lastResponse='';
    for(let attempt=0;attempt<50;attempt++) {
      try {const response=await fetch(`http://127.0.0.1:${port}/login`);if(response.status===200){ready=true;break;}lastResponse=`HTTP ${response.status}: ${await response.text()}`;} catch {}
      await new Promise(resolve=>setTimeout(resolve,100));
    }
    if(!ready) throw new Error('Browser test HTTP server failed to start: '+lastResponse+'\n'+serverLog);
    const child=spawn(process.execPath,[require.resolve('@playwright/test/cli'),'test','--config',join(__dirname,'playwright.config.cjs')],{cwd:repo,env,stdio:'inherit'});
    const status=await new Promise(resolve=>child.on('exit',resolve));
    if(status!==0) process.exitCode=status || 1;
  } catch(error) {console.error(error);process.exitCode=1;}
  finally {if(server && server.exitCode===null){server.kill();await new Promise(resolve=>server.on('exit',resolve));}rmSync(root,{recursive:true,force:true});}
})();
