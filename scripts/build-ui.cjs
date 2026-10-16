const {readFileSync, writeFileSync} = require('node:fs');
const {resolve} = require('node:path');
const postcss = require('postcss');
const tailwind = require('@tailwindcss/postcss');

(async () => {
    const root = resolve(__dirname, '..');
    const from = resolve(root, 'resources/css/app.css');
    const to = resolve(root, 'public/vod.css');
    const result = await postcss([tailwind({base: root, optimize: true})])
        .process(readFileSync(from, 'utf8'), {from, to, map: false});
    for (const warning of result.warnings()) console.warn(warning.toString());
    writeFileSync(to, result.css);
    console.log('CSS Tailwind compilé : public/vod.css');
})().catch(error => { console.error(error); process.exitCode = 1; });
