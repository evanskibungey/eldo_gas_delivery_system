#!/usr/bin/env node
/*
 * Guards the production asset build.
 *
 * Assets are built locally and committed, so the VITE_* values compiled into
 * the bundle come from THIS machine's env files -- not from Forge. The failure
 * that motivates this script: build with a local .env, push, and the live admin
 * panel tries to open a WebSocket to ws://127.0.0.1:8080. Nothing errors on the
 * server, nothing appears in any log; the panel just quietly falls back to
 * polling and the new-order chime never fires again.
 *
 * Vite resolves .env then .env.production for `vite build`, with the latter
 * winning. This script resolves them the same way and refuses to build when the
 * result would not work in production.
 *
 * Checks:
 *   1. VITE_REVERB_APP_KEY is present     -- empty means Echo is never built
 *   2. VITE_REVERB_HOST is a real host    -- not localhost/127.0.0.1/0.0.0.0
 *   3. VITE_REVERB_SCHEME/PORT agree      -- https must pair with 443
 *   4. .env.production holds only VITE_*  -- it is committed, so a DB password
 *                                            landing in it would be published
 *
 * Skip for a deliberate local-only build:  SKIP_BUILD_ENV_CHECK=1 npm run build
 */
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();

if (process.env.SKIP_BUILD_ENV_CHECK === '1') {
    console.log('Build env check skipped (SKIP_BUILD_ENV_CHECK=1).');
    process.exit(0);
}

/** Minimal KEY=VALUE parser. Enough for the shape Laravel and Vite write. */
function parseEnv(file) {
    if (!existsSync(file)) return {};

    const out = {};

    for (const raw of readFileSync(file, 'utf8').split(/\r?\n/)) {
        const line = raw.trim();
        if (!line || line.startsWith('#')) continue;

        const eq = line.indexOf('=');
        if (eq === -1) continue;

        const key = line.slice(0, eq).trim();
        let value = line.slice(eq + 1).trim();

        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }

        out[key] = value;
    }

    return out;
}

/** Resolve ${VAR} the way both dotenv-expand and phpdotenv do. */
function expand(env) {
    const resolved = { ...env };

    for (const [key, value] of Object.entries(resolved)) {
        resolved[key] = value.replace(/\$\{([A-Z0-9_]+)\}/gi, (_, name) => resolved[name] ?? '');
    }

    return resolved;
}

const base = parseEnv(join(ROOT, '.env'));
const prod = parseEnv(join(ROOT, '.env.production'));
const env = expand({ ...base, ...prod });

const errors = [];

// 4. .env.production is committed to git. Nothing secret may live in it.
const leaked = Object.keys(prod).filter((key) => !key.startsWith('VITE_'));
if (leaked.length > 0) {
    errors.push(
        `.env.production is committed to git and must contain only VITE_* keys.\n` +
        `     Found: ${leaked.join(', ')}\n` +
        `     Move these to the Forge environment editor instead.`
    );
}

// 1. No key means bootstrap.js never constructs Echo at all.
if (!env.VITE_REVERB_APP_KEY) {
    errors.push(
        'VITE_REVERB_APP_KEY is empty. Echo will not be built and the admin\n' +
        '     panel will poll every 30s with no new-order sound or banner.'
    );
}

// 2. A local host in a shipped bundle is the silent killer this script exists for.
const host = env.VITE_REVERB_HOST ?? '';
const localHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];

if (!host) {
    errors.push('VITE_REVERB_HOST is empty.');
} else if (localHosts.includes(host)) {
    errors.push(
        `VITE_REVERB_HOST is "${host}" -- a local address baked into a bundle\n` +
        `     that will be served to real browsers. Set the public domain in\n` +
        `     .env.production (0.0.0.0 is a bind address, never a destination).`
    );
}

// 3. Scheme and port have to agree or the browser dials the wrong place.
const scheme = env.VITE_REVERB_SCHEME ?? '';
const port = env.VITE_REVERB_PORT ?? '';

if (scheme === 'https' && port && port !== '443') {
    errors.push(
        `VITE_REVERB_SCHEME is https but VITE_REVERB_PORT is ${port}.\n` +
        `     Behind Nginx this should be 443 -- Nginx proxies through to Reverb.`
    );
}

if (errors.length > 0) {
    console.error('\n  Production asset build blocked:\n');
    for (const error of errors) console.error(`  -> ${error}\n`);
    console.error('  Fix .env.production, or run a local-only build with:');
    console.error('  SKIP_BUILD_ENV_CHECK=1 npm run build\n');
    process.exit(1);
}

console.log(`Build env OK -- Reverb target ${scheme || 'https'}://${host}:${port || '443'}`);
