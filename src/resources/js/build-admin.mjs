import { copyFile, mkdir, writeFile } from 'node:fs/promises';

const output = 'public/build/admin';
await mkdir(output, { recursive: true });
await copyFile('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2', `${output}/inter-latin.woff2`);
await copyFile('node_modules/@fontsource-variable/inter/LICENSE', `${output}/inter-LICENSE.txt`);
await writeFile(`${output}/fonts.css`, `@font-face {
    font-family: Inter;
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('./inter-latin.woff2') format('woff2');
}\n`);

