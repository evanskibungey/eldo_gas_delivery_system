#!/usr/bin/env node
/*
 * Generates the default new-order alert sound at public/sounds/new-order.wav.
 *
 * Committed output, run rarely — this exists so the shipped audio has
 * provenance rather than being an unexplained binary blob. To use your own
 * sound instead, drop an MP3 at public/sounds/new-order.mp3; the player prefers
 * it and never touches this file.
 *
 * A rising two-note bell (A5 -> E6) with a decaying harmonic, chosen to cut
 * through a noisy shop without being shrill. Mono 44.1kHz 16-bit PCM, which
 * every browser decodes natively with no codec questions.
 *
 * Usage:  node scripts/make-notification-sound.mjs
 */
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const RATE = 44100;
const DURATION = 1.1;
const OUT_DIR = join(process.cwd(), 'public', 'sounds');
const OUT_FILE = join(OUT_DIR, 'new-order.wav');

/** One struck-bell note: fundamental + a quieter octave, exponential decay. */
function note(buffer, freq, startSec, decaySec, peak) {
    const start = Math.floor(startSec * RATE);
    const length = Math.floor(decaySec * RATE);

    for (let i = 0; i < length; i++) {
        const index = start + i;
        if (index >= buffer.length) break;

        const t = i / RATE;
        // Exponential decay reads as a struck bell; linear reads as a fade-out.
        const envelope = Math.exp(-t * (3.2 / decaySec));
        // Short attack ramp so the onset does not click.
        const attack = Math.min(1, t / 0.006);

        const fundamental = Math.sin(2 * Math.PI * freq * t);
        const octave = 0.28 * Math.sin(2 * Math.PI * freq * 2 * t);

        buffer[index] += (fundamental + octave) * envelope * attack * peak;
    }
}

const samples = new Float32Array(Math.ceil(DURATION * RATE));

note(samples, 880.0, 0.00, 0.34, 0.42);   // A5
note(samples, 1318.5, 0.16, 0.62, 0.38);  // E6

// Guard against the two notes summing past full scale.
let loudest = 0;
for (const sample of samples) loudest = Math.max(loudest, Math.abs(sample));
const gain = loudest > 0 ? Math.min(1, 0.89 / loudest) : 1;

const pcm = Buffer.alloc(samples.length * 2);
for (let i = 0; i < samples.length; i++) {
    const clamped = Math.max(-1, Math.min(1, samples[i] * gain));
    pcm.writeInt16LE(Math.round(clamped * 32767), i * 2);
}

const header = Buffer.alloc(44);
header.write('RIFF', 0);
header.writeUInt32LE(36 + pcm.length, 4);
header.write('WAVE', 8);
header.write('fmt ', 12);
header.writeUInt32LE(16, 16);        // PCM chunk size
header.writeUInt16LE(1, 20);         // format: PCM
header.writeUInt16LE(1, 22);         // channels: mono
header.writeUInt32LE(RATE, 24);
header.writeUInt32LE(RATE * 2, 28);  // byte rate
header.writeUInt16LE(2, 32);         // block align
header.writeUInt16LE(16, 34);        // bits per sample
header.write('data', 36);
header.writeUInt32LE(pcm.length, 40);

mkdirSync(OUT_DIR, { recursive: true });
writeFileSync(OUT_FILE, Buffer.concat([header, pcm]));

const kb = ((header.length + pcm.length) / 1024).toFixed(1);
console.log(`Wrote ${OUT_FILE} (${kb} KB)`);
