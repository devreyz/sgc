const fs = require('fs');
const path = require('path');
const { compile } = require('/opt/nvm/versions/node/v22.16.0/lib/node_modules/tailwindcss/dist/lib.js');

const root = __dirname;
const tailwindRoot = '/opt/nvm/versions/node/v22.16.0/lib/node_modules/tailwindcss';

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) return walk(full);
    return [full];
  });
}

const sources = walk(root)
  .filter((file) => /\.(html|js)$/.test(file))
  .filter((file) => !file.endsWith('build-tailwind.js'))
  .map((file) => fs.readFileSync(file, 'utf8'));

const theme = fs.readFileSync(path.join(tailwindRoot, 'theme.css'), 'utf8');
const preflight = fs.readFileSync(path.join(tailwindRoot, 'preflight.css'), 'utf8');
const css = theme + '\n' + preflight + '\n' + fs.readFileSync(path.join(root, 'src.css'), 'utf8');

const candidates = new Set();

for (const source of sources) {
  const classRe = /class(?:Name)?\s*=\s*["'`]([^"'`]+)["'`]/g;
  let m;
  while ((m = classRe.exec(source))) {
    m[1].split(/\s+/).filter(Boolean).forEach((c) => candidates.add(c));
  }
  const dynamic = source.match(/\b(?:hidden|block|grid|flex|overflow-hidden)\b/g) || [];
  dynamic.forEach((c) => candidates.add(c));
}

['hidden','block','grid','flex','overflow-hidden','opacity-0','opacity-100'].forEach((c) => candidates.add(c));

(async () => {
  const compiler = await compile(css);
  const out = compiler.build([...candidates]);
  fs.writeFileSync(path.join(root, 'assets/styles.css'), out);
  console.log(`Built ${candidates.size} Tailwind candidates -> ${out.length} bytes`);
})();
