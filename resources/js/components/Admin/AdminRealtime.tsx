import { router } from '@inertiajs/react';
import {
    createContext, useCallback, useContext, useEffect, useMemo, useRef, useState,
    type PropsWithChildren,
} from 'react';
import { startRinging, stopRinging, unlockSoundOnFirstGesture } from '@/lib/notificationSound';
import NewOrderAlertStack, { type NewOrderAlert } from './NewOrderAlertStack';

// ── Broadcast payloads ────────────────────────────────────────────────────────
// Mirror OrderPlacedEvent::broadcastWith() and
// OrderStatusUpdatedEvent::broadcastWith() on the PHP side.

export interface NewOrderPayload {
    id:             number;
    order_number:   string;
    status:         string;
    order_type:     'swap' | 'new_cylinder';
    total_amount:   number;
    payment_method: 'cash' | 'mpesa';
    size_name:      string | null;
    brand_name:     string | null;
    customer_name:  string | null;
    customer_phone: string | null;
    address:        string | null;
    created_ago:    string;
    is_reoffer:     boolean;
}

export interface OrderStatusPayload {
    order_id:       number;
    order_number:   string;
    status:         string;
    payment_status: string;
    has_issue:      boolean;
    issue_type:     string | null;
    rider_name:     string | null;
    updated_at:     string | null;
}

export type AdminOrderEvent =
    | { kind: 'placed'; order: NewOrderPayload }
    | { kind: 'status'; order: OrderStatusPayload };

type EventHandler = (event: AdminOrderEvent) => void;

// ── Timings ───────────────────────────────────────────────────────────────────

/** Socket is down: this is the only way orders arrive, so poll briskly. */
const POLL_DISCONNECTED_MS = 30_000;
/**
 * Socket is up. Still polls, because "connected" only proves Reverb is
 * reachable — it says nothing about whether the queue worker that actually
 * publishes the broadcast is alive. A dead worker is silent otherwise.
 */
const POLL_CONNECTED_MS = 90_000;
/** Collapse a burst of events into one request. */
const REFRESH_DEBOUNCE_MS = 1_200;
/** Badge-only refresh ceiling on pages that are not the dispatch board. */
const BADGE_REFRESH_MIN_GAP_MS = 30_000;

const BADGE_PROPS = ['pending_orders_count'];

interface AdminRealtimeValue {
    /** Reverb socket is connected. False also when Echo was never built. */
    connected: boolean;
    /** Echo exists at all — false means the build had no Reverb key. */
    echoAvailable: boolean;
    /** Register a listener for admin order broadcasts. */
    subscribe: (handler: EventHandler) => () => void;
    /** Declare which Inertia props this page wants refreshed on activity. */
    registerRefreshProps: (props: string[]) => () => void;
}

const AdminRealtimeContext = createContext<AdminRealtimeValue | null>(null);

export function AdminRealtimeProvider({ children }: PropsWithChildren) {
    const echoAvailable = typeof window !== 'undefined' && !!window.Echo;

    const [connected, setConnected] = useState(false);
    const [alerts, setAlerts]       = useState<NewOrderAlert[]>([]);

    const handlers      = useRef(new Set<EventHandler>());
    const refreshProps  = useRef<string[] | null>(null);
    const debounceTimer = useRef<number | null>(null);
    const lastBadgeAt   = useRef(0);
    const seenOrderIds  = useRef(new Set<number>());

    // ── Refresh coordination ──────────────────────────────────────────────────

    const runRefresh = useCallback(() => {
        const props = refreshProps.current;

        if (props && props.length > 0) {
            router.reload({ only: props });
            return;
        }

        // Not on the dispatch board. The banner renders straight from the
        // broadcast payload, so the only thing needing a round-trip is the
        // sidebar badge — and that can be rate-limited hard.
        const now = Date.now();
        if (now - lastBadgeAt.current < BADGE_REFRESH_MIN_GAP_MS) return;
        lastBadgeAt.current = now;
        router.reload({ only: BADGE_PROPS });
    }, []);

    const scheduleRefresh = useCallback(() => {
        if (debounceTimer.current) window.clearTimeout(debounceTimer.current);
        debounceTimer.current = window.setTimeout(runRefresh, REFRESH_DEBOUNCE_MS);
    }, [runRefresh]);

    const registerRefreshProps = useCallback((props: string[]) => {
        refreshProps.current = props;
        return () => {
            refreshProps.current = null;
        };
    }, []);

    const subscribe = useCallback((handler: EventHandler) => {
        handlers.current.add(handler);
        return () => {
            handlers.current.delete(handler);
        };
    }, []);

    const emit = useCallback((event: AdminOrderEvent) => {
        handlers.current.forEach(handler => handler(event));
    }, []);

    // ── Alerts ────────────────────────────────────────────────────────────────

    const dismissAlert = useCallback((id: number) => {
        setAlerts(current => current.filter(alert => alert.order.id !== id));
    }, []);

    const dismissAllAlerts = useCallback(() => setAlerts([]), []);

    const raiseAlert = useCallback((order: NewOrderPayload) => {
        setAlerts(current => {
            // A poll and a broadcast can surface the same order; and a rider
            // decline re-fires the placed event. Announce each order once.
            if (current.some(alert => alert.order.id === order.id)) return current;
            // Newest first. Capped only to bound memory during an outage — the
            // alarm is acknowledged by hand, so nothing is auto-expired.
            return [{ order, receivedAt: Date.now() }, ...current].slice(0, 20);
        });

        notifyDesktop(order);
    }, []);

    // Ring for as long as ANY order is unacknowledged, rather than playing once
    // and hoping somebody was at the screen. Driven off the alert list so every
    // path that clears an alert — dismiss, dismiss-all, clicking through to the
    // order — stops the alarm without having to remember to.
    useEffect(() => {
        if (alerts.length > 0) {
            startRinging();
        } else {
            stopRinging();
        }
    }, [alerts.length]);

    // A navigation away (or a full page swap) must not leave the alarm ringing
    // with nothing on screen to silence it.
    useEffect(() => stopRinging, []);

    // ── Channel subscription ──────────────────────────────────────────────────

    useEffect(() => {
        unlockSoundOnFirstGesture();

        const echo = window.Echo;
        if (!echo) {
            // No Reverb key at build time. Leave `connected` false so the
            // polling fallback below carries the whole load.
            return;
        }

        const channel = echo.private('admin.orders');

        channel.listen('.order.placed', (payload: NewOrderPayload) => {
            const isNew = !payload.is_reoffer && !seenOrderIds.current.has(payload.id);
            seenOrderIds.current.add(payload.id);

            if (isNew) raiseAlert(payload);

            emit({ kind: 'placed', order: payload });
            scheduleRefresh();
        });

        channel.listen('.order.status_updated', (payload: OrderStatusPayload) => {
            emit({ kind: 'status', order: payload });
            scheduleRefresh();
        });

        const pusher = (echo.connector as any)?.pusher;
        let onStateChange: ((states: { current: string }) => void) | null = null;

        if (pusher) {
            onStateChange = ({ current }: { current: string }) => setConnected(current === 'connected');
            pusher.connection.bind('state_change', onStateChange);
            // Mount can happen after the socket is already up or already down.
            setConnected(pusher.connection.state === 'connected');
        }

        return () => {
            // Unbinding matters: Echo.leave() drops the channel but leaves this
            // connection listener attached, so every visit to an admin page
            // used to stack another one.
            if (pusher && onStateChange) pusher.connection.unbind('state_change', onStateChange);
            echo.leave('admin.orders');
            if (debounceTimer.current) window.clearTimeout(debounceTimer.current);
        };
    }, [emit, raiseAlert, scheduleRefresh]);

    // ── Polling safety net ────────────────────────────────────────────────────

    useEffect(() => {
        const every = connected ? POLL_CONNECTED_MS : POLL_DISCONNECTED_MS;
        const interval = window.setInterval(runRefresh, every);
        return () => window.clearInterval(interval);
    }, [connected, runRefresh]);

    const value = useMemo<AdminRealtimeValue>(() => ({
        connected,
        echoAvailable,
        subscribe,
        registerRefreshProps,
    }), [connected, echoAvailable, subscribe, registerRefreshProps]);

    return (
        <AdminRealtimeContext.Provider value={value}>
            {children}
            <NewOrderAlertStack
                alerts={alerts}
                onDismiss={dismissAlert}
                onDismissAll={dismissAllAlerts}
            />
        </AdminRealtimeContext.Provider>
    );
}

// ── Desktop notifications ─────────────────────────────────────────────────────

function notifyDesktop(order: NewOrderPayload): void {
    if (typeof Notification === 'undefined') return;
    if (Notification.permission !== 'granted') return;
    // Only when the panel is not the visible tab — otherwise the in-page banner
    // already has the admin's attention and a second copy is just noise.
    if (typeof document !== 'undefined' && document.visibilityState === 'visible') return;

    try {
        const where = order.address ? ' · ' + order.address : '';
        const amount = order.total_amount.toLocaleString();

        new Notification('New order ' + order.order_number, {
            body: [
                order.customer_name ?? 'Customer',
                order.size_name ?? 'Cylinder',
                'KES ' + amount,
            ].join(' · ') + where,
            tag: 'eldogas-order-' + order.id,
        });
    } catch {
        // Some browsers throw when constructing notifications outside a
        // service worker. The in-page banner still fired.
    }
}

// ── Hooks ─────────────────────────────────────────────────────────────────────

/**
 * Never throws.
 *
 * These hooks used to throw when no provider was above them, which turned one
 * misplaced hook call into a blank white admin panel — the page component sits
 * ABOVE the layout that renders the provider, so a hook called in a page body
 * finds no context. Losing live updates is a bad day; losing the whole dispatch
 * board mid-shift is a business outage. Degrade instead.
 */
function useAdminRealtimeContext(): AdminRealtimeValue {
    const value = useContext(AdminRealtimeContext);

    // Stable identity so effects depending on these do not loop.
    const fallback = useRef<AdminRealtimeValue>({
        connected: false,
        echoAvailable: false,
        subscribe: () => () => undefined,
        registerRefreshProps: () => () => undefined,
    });

    if (!value) {
        // Warn in production too — this means live updates are silently off for
        // whoever is looking at the page, which is worth being able to diagnose
        // from a screenshot of the console.
        console.warn(
            '[AdminRealtime] hook used outside AdminRealtimeProvider — live updates disabled here. ' +
            'Page components render ABOVE AdminLayout: pass liveRefresh to AdminLayout instead.',
        );

        return fallback.current;
    }

    return value;
}

/** Connection state, for the "live updates offline" indicator. */
export function useAdminRealtime(): { connected: boolean; echoAvailable: boolean } {
    const { connected, echoAvailable } = useAdminRealtimeContext();
    return { connected, echoAvailable };
}

/** Run `handler` on every admin order broadcast while this component is mounted. */
export function useAdminOrderEvents(handler: EventHandler): void {
    const { subscribe } = useAdminRealtimeContext();
    const stable = useRef(handler);
    stable.current = handler;

    useEffect(() => subscribe(event => stable.current(event)), [subscribe]);
}

/**
 * Declare the Inertia props this page wants refreshed when orders change.
 * Pages that do not call this get a rate-limited sidebar-badge refresh instead.
 */
export function useAdminLiveRefresh(props: string[]): void {
    const { registerRefreshProps } = useAdminRealtimeContext();
    const key = props.join(',');

    useEffect(
        () => registerRefreshProps(key.split(',')),
        [key, registerRefreshProps],
    );
}
