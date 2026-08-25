/**
 * New-order alert chime.
 *
 * Synthesised with the Web Audio API rather than loaded from an mp3: no asset
 * to ship, cache-bust or 404, and it stays audible over a noisy shop floor.
 *
 * Browsers refuse to start an AudioContext until the user has interacted with
 * the page, so the context is created lazily and unlocked on the first click or
 * keypress. Until that happens `play()` is a no-op instead of an exception.
 */

const MUTE_KEY = 'eldogas.admin.orderSound.muted';

let ctx: AudioContext | null = null;
let unlockBound = false;

type AudioContextCtor = typeof AudioContext;

function audioContextCtor(): AudioContextCtor | null {
    if (typeof window === 'undefined') return null;
    return window.AudioContext ?? (window as any).webkitAudioContext ?? null;
}

function context(): AudioContext | null {
    const Ctor = audioContextCtor();
    if (!Ctor) return null;

    if (!ctx) {
        try {
            ctx = new Ctor();
        } catch {
            return null;
        }
    }

    return ctx;
}

/**
 * Resume the audio context on the first user gesture. Safe to call repeatedly —
 * it binds one set of listeners and removes them once they have fired.
 */
export function unlockSoundOnFirstGesture(): void {
    if (unlockBound || typeof window === 'undefined') return;
    unlockBound = true;

    const unlock = () => {
        const audio = context();
        if (audio && audio.state === 'suspended') {
            void audio.resume().catch(() => undefined);
        }
        window.removeEventListener('pointerdown', unlock);
        window.removeEventListener('keydown', unlock);
    };

    window.addEventListener('pointerdown', unlock, { once: false });
    window.addEventListener('keydown', unlock, { once: false });
}

export function isMuted(): boolean {
    try {
        return window.localStorage.getItem(MUTE_KEY) === '1';
    } catch {
        // Private windows and blocked site data throw on access.
        return false;
    }
}

export function setMuted(muted: boolean): void {
    try {
        window.localStorage.setItem(MUTE_KEY, muted ? '1' : '0');
    } catch {
        // Preference simply will not persist — not worth failing the alert over.
    }
}

/** One bell-ish note. */
function tone(audio: AudioContext, freq: number, startAt: number, duration: number, peak: number): void {
    const osc  = audio.createOscillator();
    const gain = audio.createGain();

    osc.type            = 'sine';
    osc.frequency.value = freq;

    // Percussive envelope: near-instant attack, exponential decay. A raw
    // gate would click audibly at both ends.
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(peak, startAt + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    osc.connect(gain).connect(audio.destination);
    osc.start(startAt);
    osc.stop(startAt + duration + 0.02);
}

/**
 * Two-note rising chime. `urgent` repeats it once for orders that need someone
 * to act now rather than merely be aware.
 */
export function playNewOrderChime(urgent = false): void {
    if (isMuted()) return;

    const audio = context();
    if (!audio) return;

    if (audio.state === 'suspended') {
        // No gesture yet — resume and let the next alert be the audible one.
        void audio.resume().catch(() => undefined);
        if (audio.state === 'suspended') return;
    }

    const now = audio.currentTime;

    tone(audio, 880,  now,        0.16, 0.22); // A5
    tone(audio, 1318, now + 0.13, 0.30, 0.20); // E6

    if (urgent) {
        tone(audio, 880,  now + 0.46, 0.16, 0.22);
        tone(audio, 1318, now + 0.59, 0.34, 0.20);
    }
}
