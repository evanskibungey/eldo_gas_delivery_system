#!/usr/bin/env node
/*
 * Guards against the encoding damage that has hit this repo twice.
 *
 * Failure mode: a Windows tool reads a UTF-8 source file as Latin-1/CP1252 and
 * re-saves it as UTF-8. Every multi-byte character expands into mojibake --
 * (c) becomes A-tilde-(c), the ellipsis becomes a-circumflex-euro-broken-bar,
 * and box-drawing rules become three-character sludge. PowerShell is the usual
 * culprit: Set-Content defaults to the system ANSI codepage, and Out-File / >
 * write a UTF-8 BOM. A bulk find-and-replace run through either corrupts every
 * file it touches.
 *
 * Checks:
 *   1. mojibake  -- non-ASCII runs that decode cleanly as misread UTF-8
 *   2. BOM       -- a UTF-8 byte-order mark (breaks PHP output, masks issue 1)
 *   3. C1 chars  -- U+0080..U+009F, always residue from a bad round trip
 *
 * This file is deliberately pure ASCII -- every pattern is built from
 * String.fromCharCode rather than a literal. Raw high bytes in here would be
 * corrupted by exactly the failure this script exists to catch, silently
 * disabling it. It also checks itself.
 *
 * Usage:  node scripts/check-encoding.mjs [--fix]
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative, extname } from 'node:path';

const ROOT = process.cwd();
const FIX = process.argv.includes('--fix');

const SKIP_DIRS = new Set(['node_modules', 'vendor', '.git', 'public', 'storage', 'dist']);
const EXTS = new Set(['.tsx', '.ts', '.jsx', '.js', '.mjs', '.php', '.css', '.json', '.md']);

const BOM = String.fromCharCode(0xFEFF);
const NON_ASCII_RUN = new RegExp('[' + String.fromCharCode(0x80) + '-' + String.fromCharCode(0xFFFF) + ']{2,}', 'g');
const C1_CONTROL = new RegExp('[' + String.fromCharCode(0x80) + '-' + String.fromCharCode(0x9F) + ']', 'g');

// CP1252 fills bytes 0x80-0x9F that Latin-1 leaves undefined.
const CP1252 = {
    0x20AC: 0x80, 0x201A: 0x82, 0x0192: 0x83, 0x201E: 0x84, 0x2026: 0x85,
    0x2020: 0x86, 0x2021: 0x87, 0x02C6: 0x88, 0x2030: 0x89, 0x0160: 0x8A,
    0x2039: 0x8B, 0x0152: 0x8C, 0x017D: 0x8E, 0x2018: 0x91, 0x2019: 0x92,
    0x201C: 0x93, 0x201D: 0x94, 0x2022: 0x95, 0x2013: 0x96, 0x2014: 0x97,
    0x02DC: 0x98, 0x2122: 0x99, 0x0161: 0x9A, 0x203A: 0x9B, 0x0153: 0x9C,
    0x017E: 0x9E, 0x0178: 0x9F,
};

const decoder = new TextDecoder('utf-8', { fatal: true });

/** Reverse one mojibake run, or null if the run is legitimate text. */
function undo(run) {
    const bytes = [];
    for (const ch of run) {
        const cp = ch.codePointAt(0);
        const b = cp <= 0xFF ? cp : CP1252[cp];
        if (b === undefined) return null;   // not single-byte representable
        bytes.push(b);
    }
    try {
        const decoded = decoder.decode(new Uint8Array(bytes));
        // Mojibake always expands; a shorter decode means we reversed it.
        return decoded.length < run.length ? decoded : null;
    } catch {
        return null;                        // invalid UTF-8 -- genuine text
    }
}

function walk(dir) {
    const out = [];
    for (const entry of readdirSync(dir)) {
        if (SKIP_DIRS.has(entry) || entry.startsWith('.')) continue;
        const full = join(dir, entry);
        let st;
        try { st = statSync(full); } catch { continue; }
        if (st.isDirectory()) out.push(...walk(full));
        else if (EXTS.has(extname(entry))) out.push(full);
    }
    return out;
}

const problems = [];

for (const path of walk(ROOT)) {
    const raw = readFileSync(path);
    const hasBom = raw[0] === 0xEF && raw[1] === 0xBB && raw[2] === 0xBF;
    const text = raw.toString('utf8').replace(new RegExp('^' + BOM), '');
    const rel = relative(ROOT, path).replace(/\\/g, '/');

    const repaired = text.replace(NON_ASCII_RUN, (run) => undo(run) ?? run);
    const fixed = repaired.replace(C1_CONTROL, '');
    const damaged = fixed !== text;

    if (!damaged && !hasBom) continue;

    if (damaged) {
        const sample = [...text.matchAll(NON_ASCII_RUN)]
            .map(m => [m[0], undo(m[0])])
            .find(([, d]) => d !== null);
        problems.push(sample
            ? `${rel}: mojibake -- ${JSON.stringify(sample[0])} should be ${JSON.stringify(sample[1])}`
            : `${rel}: stray C1 control character`);
    }
    if (hasBom) problems.push(`${rel}: UTF-8 BOM`);

    if (FIX) writeFileSync(path, Buffer.from(fixed, 'utf8'));
}

if (problems.length === 0) {
    console.log('Encoding OK -- no mojibake, BOMs or C1 controls.');
    process.exit(0);
}

if (FIX) {
    console.log(`Fixed ${problems.length} issue(s):`);
    problems.forEach(p => console.log('  ' + p));
    process.exit(0);
}

console.error(`Encoding check failed -- ${problems.length} issue(s):\n`);
problems.forEach(p => console.error('  ' + p));
console.error('\nRepair with:  npm run check:encoding -- --fix');
console.error('Cause: source files piped through PowerShell Set-Content / Out-File.');
console.error('Use Set-Content -Encoding utf8NoBOM, or do bulk edits in Node or Git Bash.');
process.exit(1);
