/**
 * New-order alert sound.
 *
 * Plays an audio file, falling back to a synthesised chime if the file is
 * missing or blocked. To use your own sound, drop an MP3 at
 * public/sounds/new-order.mp3 — it is preferred automatically, no code change.
 *
 * The hard part is not playing audio, it is browser autoplay policy: nothing
 * may make sound until the user has interacted with the page. An admin watching
 * the dispatch board has usually not clicked anything, so the alert that
 * matters most is the one most likely to be blocked. Two things follow:
 *
 *   1. The first real gesture primes the audio (silent play/pause), which is
 *      the only reliable way to arm playback for later.
 *   2. isSoundArmed() lets the UI say "click to enable sound" rather than
 *      failing silently, which is how this went unnoticed for so long.
 */

const MUTE_KEY = 'eldogas.admin.orderSound.muted';

/** First that loads wins, so dropping in an MP3 overrides the default. */
const SOUND_URLS = ['/sounds/new-order.mp3', '/sounds/new-order.wav'];

/**
 * Longest sound that still gets the urgent double-play. Anything above this is
 * already attention-grabbing on its own; repeating it is just noise.
 */
const REPEAT_MAX_SECONDS = 2;

let element: HTMLAudioElement | null = null;
let ctx: AudioContext | null = null;
let unlockBound = false;
let armed = false;

// ── Audio element ─────────────────────────────────────────────────────────────

function audioElement(): HTMLAudioElement | null {
    if (typeof Audio === 'undefined') return null;

    if (!element) {
        element = new Audio();
        element.preload = 'auto';
        // Alert, not media: should not pause the user's music or take over
        // media keys on a phone.
        element.loop = false;
        element.volume = 1;

        let index = 0;
        const tryNext = () => {
            if (index >= SOUND_URLS.length) return;
            element!.src = SOUND_URLS[index++];
        };

        // A missing file fires `error`; step to the next candidate.
        element.addEventListener('error', tryNext);
        tryNext();
    }

    return element;
}

// ── Web Audio fallback ────────────────────────────────────────────────────────

function context(): AudioContext | null {
    if (typeof window === 'undefined') return null;

    const Ctor = window.AudioContext ?? (window as any).webkitAudioContext;
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

/** One bell-ish note, used only when the audio file cannot play. */
function tone(audio: AudioContext, freq: number, startAt: number, duration: number, peak: number): void {
    const osc = audio.createOscillator();
    const gain = audio.createGain();

    osc.type = 'sine';
    osc.frequency.value = freq;

    // Percussive envelope: near-instant attack, exponential decay. A raw gate
    // would click audibly at both ends.
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(peak, startAt + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    osc.connect(gain).connect(audio.destination);
    osc.start(startAt);
    osc.stop(startAt + duration + 0.02);
}

function playSynthesised(urgent: boolean): void {
    const audio = context();
    if (!audio || audio.state === 'suspended') return;

    const now = audio.currentTime;

    tone(audio, 880, now, 0.16, 0.22);        // A5
    tone(audio, 1318, now + 0.13, 0.30, 0.20); // E6

    if (urgent) {
        tone(audio, 880, now + 0.46, 0.16, 0.22);
        tone(audio, 1318, now + 0.59, 0.34, 0.20);
    }
}

// ── Unlocking ─────────────────────────────────────────────────────────────────

/** True once audio is actually allowed to play without a gesture. */
export function isSoundArmed(): boolean {
    return armed;
}

/**
 * Arm audio using the user gesture currently being handled.
 *
 * Safe to call directly from a click handler — that is the only context in
 * which browsers grant permission.
 */
export function armSound(): void {
    const audio = context();
    if (audio && audio.state === 'suspended') {
        void audio.resume().catch(() => undefined);
    }

    const el = audioElement();
    if (!el) return;

    // Silent play/pause is the standard priming trick: it consumes the gesture
    // and leaves the element permitted to play later without one.
    const wasMuted = el.muted;
    el.muted = true;

    const played = el.play();

    if (played && typeof played.then === 'function') {
        played
            .then(() => {
                el.pause();
                el.currentTime = 0;
                el.muted = wasMuted;
                armed = true;
            })
            .catch(() => {
                el.muted = wasMuted;
            });
    } else {
        el.pause();
        el.currentTime = 0;
        el.muted = wasMuted;
        armed = true;
    }
}

/**
 * Arm on the first click or keypress anywhere. Safe to call repeatedly — it
 * binds one set of listeners and removes them once they have fired.
 */
export function unlockSoundOnFirstGesture(): void {
    if (unlockBound || typeof window === 'undefined') return;
    unlockBound = true;

    const unlock = () => {
        armSound();
        window.removeEventListener('pointerdown', unlock);
        window.removeEventListener('keydown', unlock);
    };

    window.addEventListener('pointerdown', unlock);
    window.addEventListener('keydown', unlock);
}

// ── Mute preference ───────────────────────────────────────────────────────────

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
        // Preference will not persist — not worth failing the alert over.
    }
}

// ── Playback ──────────────────────────────────────────────────────────────────

// ── Continuous ringing ────────────────────────────────────────────────────────

let synthLoop: number | null = null;

/**
 * Ring until stopRinging() is called.
 *
 * Used while any new order is still unacknowledged: the point is that an order
 * cannot be missed because nobody happened to be at the screen in the two
 * seconds the sound played.
 *
 * Safe to call repeatedly — an already-ringing alarm is left alone rather than
 * restarted, so a second order arriving does not chop the sound.
 */
export function startRinging(): void {
    if (isMuted()) return;

    const el = audioElement();

    if (el && el.src) {
        if (!el.paused && el.loop) return; // already ringing

        el.loop = true;

        try {
            el.currentTime = 0;
        } catch {
            // Metadata not loaded yet; play() below still works.
        }

        const played = el.play();

        if (played && typeof played.then === 'function') {
            played
                .then(() => {
                    armed = true;
                    stopSynthLoop();
                })
                .catch(() => {
                    // Autoplay blocked or file unavailable — fall back to the
                    // synth, which may already be permitted.
                    armed = false;
                    startSynthLoop();
                });
        }

        return;
    }

    startSynthLoop();
}

/** Stop the alarm. Safe to call when nothing is ringing. */
export function stopRinging(): void {
    stopSynthLoop();

    if (!element) return;

    element.loop = false;
    element.pause();

    try {
        element.currentTime = 0;
    } catch {
        // Ignore: nothing to rewind.
    }
}

function startSynthLoop(): void {
    if (synthLoop !== null) return;

    playSynthesised(false);
    // Roughly the length of the synth chime plus a breath, so it reads as a
    // repeating alarm rather than a stutter.
    synthLoop = window.setInterval(() => playSynthesised(false), 1400);
}

function stopSynthLoop(): void {
    if (synthLoop === null) return;

    window.clearInterval(synthLoop);
    synthLoop = null;
}

/**
 * Play the alert once. `urgent` repeats it for short sounds.
 *
 * Used for the bell's confirmation beep. New orders use startRinging().
 *
 * Never throws and never blocks the banner: a silent alert is a degraded alert,
 * not a broken page.
 */
export function playNewOrderChime(urgent = false): void {
    if (isMuted()) return;

    const el = audioElement();

    if (!el || !el.src) {
        playSynthesised(urgent);
        return;
    }

    try {
        // Rewind so rapid consecutive orders each sound, rather than the second
        // being swallowed by the first still playing.
        el.currentTime = 0;
    } catch {
        // Throws if metadata has not loaded yet; play() below still works.
    }

    const played = el.play();

    if (played && typeof played.then === 'function') {
        played
            .then(() => {
                armed = true;

                // Repeat only for a short sound. The double strike exists to
                // add urgency to a ~1s chime; on a 4s clip it just means eight
                // seconds of alarm per order, which people mute.
                const duration = Number.isFinite(el.duration) ? el.duration : 0;

                if (urgent && duration > 0 && duration <= REPEAT_MAX_SECONDS) {
                    const again = () => {
                        el.removeEventListener('ended', again);
                        void el.play().catch(() => undefined);
                    };
                    el.addEventListener('ended', again);
                }
            })
            .catch(() => {
                // Blocked by autoplay policy, or the file failed to load. Try
                // the synth, which may already be resumed even when the element
                // is not permitted.
                armed = false;
                playSynthesised(urgent);
            });
    }
}
