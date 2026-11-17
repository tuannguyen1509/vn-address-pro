import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const translations = JSON.parse(fs.readFileSync(path.join(root, 'languages/vi.json'), 'utf8'));
const messages = new Set();
function scan(dir) {
    for (const file of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, file.name);
        if (file.isDirectory()) scan(full);
        else if (file.name.endsWith('.php')) {
            const text = fs.readFileSync(full, 'utf8');
            for (const match of text.matchAll(/\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*'((?:\\.|[^'\\])*)'\s*,\s*'vn-address-pro'/g)) {
                const value = match[1].replace(/\\'/g, "'").replace(/\\\\/g, '\\');
                if (/^[\x20-\x7e]/.test(value) && !/[à-ỹ]/i.test(value)) messages.add(value);
            }
        }
    }
}
scan(path.join(root, 'src'));
const missing = [...messages].filter(message => !translations[message]);
if (missing.length) throw new Error('Missing Vietnamese translations:\n' + missing.join('\n'));
const version = fs.readFileSync(path.join(root, 'vn-address-api.php'), 'utf8').match(/Version:\s*([\d.]+)/)[1];
const header = `Project-Id-Version: VN Address Pro ${version}\nLanguage: vi\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nPlural-Forms: nplurals=1; plural=0;\n`;
const entries = [['', header], ...Object.entries(translations)].sort((a, b) => Buffer.compare(Buffer.from(a[0]), Buffer.from(b[0])));
for (const [id, value] of entries) {
    if (!id) continue;
    const placeholders = text => [...text.matchAll(/%(?:\d+\$)?[dsf]/g)].map(match => match[0]).sort().join('|');
    if (placeholders(id) !== placeholders(value)) throw new Error('Placeholder mismatch: ' + id);
}
const po = entries.map(([id, value]) => `msgid ${JSON.stringify(id)}\nmsgstr ${JSON.stringify(value)}\n`).join('\n');
fs.writeFileSync(path.join(root, 'languages/vn-address-pro-vi.po'), po);
const n = entries.length, start = 28 + n * 16;
let offset = start;
const originals = entries.map(([id]) => { const data = Buffer.from(id + '\0'); const row = [data.length - 1, offset, data]; offset += data.length; return row; });
const translated = entries.map(([, value]) => { const data = Buffer.from(value + '\0'); const row = [data.length - 1, offset, data]; offset += data.length; return row; });
const mo = Buffer.alloc(offset);
for (const [i, value] of [0x950412de, 0, n, 28, 28 + n * 8, 0, 0].entries()) mo.writeUInt32LE(value, i * 4);
for (const [i, row] of [...originals, ...translated].entries()) {
    mo.writeUInt32LE(row[0], 28 + i * 8); mo.writeUInt32LE(row[1], 32 + i * 8); row[2].copy(mo, row[1]);
}
fs.writeFileSync(path.join(root, 'languages/vn-address-pro-vi.mo'), mo);
console.log(`Built Vietnamese catalog: ${n - 1} translations; ${messages.size} extracted English messages covered.`);
