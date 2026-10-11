const {defineConfig} = require('@playwright/test');
module.exports = defineConfig({
  testDir:__dirname,testMatch:'**/*.spec.cjs',fullyParallel:false,workers:1,retries:0,timeout:30000,
  outputDir: process.env.VOD_BROWSER_RESULTS || '/tmp/vod-browser-results',
  use:{baseURL:`http://127.0.0.1:${process.env.VOD_BROWSER_PORT || '18765'}`,headless:true,
    launchOptions:{...(process.env.VOD_CHROMIUM_PATH ? {executablePath:process.env.VOD_CHROMIUM_PATH} : {}),args:['--no-sandbox']}}
});
