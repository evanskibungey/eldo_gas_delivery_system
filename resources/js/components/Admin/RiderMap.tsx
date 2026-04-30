/**
 * RiderMap — real-time rider tracking map for the EldoGas admin dashboard.
 *
 * Data flow:
 *   1. Component mounts with hardcoded demo positions (Eldoret geography).
 *   2. A simulation hook advances active riders along predefined routes every
 *      2 s so the map looks live during development.
 *   3. When Laravel Echo / Reverb is available on window.Echo, incoming
 *      `rider.location.updated` events override the simulation and drive real
 *      positions from the rider mobile app.
 */

import { useEffect, useRef, useState, useCallback } from 'react';
import React from 'react';
import {
    MapContainer,
    TileLayer,
    Marker,
    Polyline,
    Popup,
    useMap,
} from 'react-leaflet';
import L from 'leaflet';
// Leaflet CSS is loaded via app.css — do NOT import here (conflicts with @tailwindcss/vite)
import { Maximize2, Truck, MapPin, Navigation } from 'lucide-react';
import { cn } from '@/lib/utils';

// ─── Types ────────────────────────────────────────────────────────────────────

export type RiderStatus = 'on_delivery' | 'available' | 'offline';

export interface RiderMapData {
    id:          number;
    name:        string;
    status:      RiderStatus;
    lat:         number;
    lng:         number;
    heading:     number;
    orderId:     string | null;
    location:    string;
    route?:      [number, number][];
    destination?: [number, number];
}

// ─── Eldoret geography ────────────────────────────────────────────────────────

const ELDORET_CENTER: [number, number] = [0.5143, 35.2698];
const DEPOT:          [number, number] = [0.5143, 35.2698];

// Realistic road-following waypoints around Eldoret
const ROUTE_LANGAS: [number, number][] = [
    [0.5143, 35.2698],
    [0.5160, 35.2706],
    [0.5178, 35.2715],
    [0.5200, 35.2724],
    [0.5225, 35.2733],
    [0.5248, 35.2740],
    [0.5267, 35.2748],
];

const ROUTE_HURUMA: [number, number][] = [
    [0.5143, 35.2698],
    [0.5128, 35.2712],
    [0.5112, 35.2740],
    [0.5098, 35.2778],
    [0.5086, 35.2818],
    [0.5075, 35.2848],
    [0.5068, 35.2862],
];

const ROUTE_KAPSERET: [number, number][] = [
    [0.5143, 35.2698],
    [0.5168, 35.2750],
    [0.5200, 35.2810],
    [0.5238, 35.2875],
    [0.5275, 35.2940],
    [0.5310, 35.3005],
    [0.5332, 35.3018],
];

// ─── Demo riders ──────────────────────────────────────────────────────────────

const DEMO_RIDERS: RiderMapData[] = [
    {
        id: 1, name: 'Brian Mutai', status: 'on_delivery',
        lat: 0.5178, lng: 35.2715, heading: 42,
        orderId: '#1042', location: 'Langas Rd',
        route: ROUTE_LANGAS, destination: [0.5267, 35.2748],
    },
    {
        id: 2, name: 'Kevin Omondi', status: 'on_delivery',
        lat: 0.5112, lng: 35.2740, heading: 218,
        orderId: '#1040', location: 'Huruma',
        route: ROUTE_HURUMA, destination: [0.5068, 35.2862],
    },
    {
        id: 3, name: 'David Njoroge', status: 'available',
        lat: 0.5150, lng: 35.2705, heading: 0,
        orderId: null, location: 'Depot',
    },
    {
        id: 4, name: 'Samuel Waweru', status: 'available',
        lat: 0.5137, lng: 35.2690, heading: 0,
        orderId: null, location: 'Depot',
    },
    {
        id: 5, name: 'Alex Otieno', status: 'offline',
        lat: 0.5128, lng: 35.2678, heading: 0,
        orderId: null, location: '—',
    },
];

// ─── Status palette ───────────────────────────────────────────────────────────

type StatusDef = { label: string; hex: string; ring: string; dimmed: boolean };

const STATUS: Record<RiderStatus, StatusDef> = {
    on_delivery: { label: 'On Delivery', hex: '#f97316', ring: '#fed7aa', dimmed: false },
    available:   { label: 'Available',   hex: '#10b981', ring: '#a7f3d0', dimmed: false },
    offline:     { label: 'Offline',     hex: '#94a3b8', ring: '#e2e8f0', dimmed: true  },
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getInitials(name: string): string {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function interpolate(pts: [number, number][], t: number): [number, number] {
    if (pts.length < 2) return pts[0];
    const seg  = pts.length - 1;
    const raw  = t * seg;
    const i    = Math.min(Math.floor(raw), seg - 1);
    const frac = raw - i;
    return [
        pts[i][0] + (pts[i + 1][0] - pts[i][0]) * frac,
        pts[i][1] + (pts[i + 1][1] - pts[i][1]) * frac,
    ];
}

// ─── One-time CSS injection ───────────────────────────────────────────────────

function ensureMapStyles() {
    const ID = 'eldogas-ridermap-css';
    if (document.getElementById(ID)) return;
    const el = document.createElement('style');
    el.id = ID;
    el.textContent = `
        @keyframes riderPulse {
            0%, 100% { transform: scale(1);   opacity: .55; }
            50%       { transform: scale(1.7); opacity: .18; }
        }
        .leaflet-container { font-family: inherit; background: #f8fafc; }

        /* Custom popup chrome */
        .rider-popup .leaflet-popup-content-wrapper {
            border-radius: 10px !important;
            box-shadow: 0 6px 24px rgba(0,0,0,.10) !important;
            padding: 0 !important; border: 1px solid #f1f5f9;
        }
        .rider-popup .leaflet-popup-content  { margin: 0 !important; }
        .rider-popup .leaflet-popup-tip      { display: none; }

        /* Zoom controls */
        .leaflet-control-zoom {
            border: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,.10) !important;
            border-radius: 8px !important; overflow: hidden;
        }
        .leaflet-control-zoom a {
            background: #fff !important; color: #475569 !important;
            width: 30px !important; height: 30px !important;
            line-height: 30px !important; font-size: 16px !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        .leaflet-control-zoom a:hover { background: #f8fafc !important; }
        .leaflet-control-attribution {
            background: rgba(255,255,255,.65) !important;
            font-size: 8px !important; backdrop-filter: blur(4px);
        }
    `;
    document.head.appendChild(el);
}

// ─── Leaflet icons ────────────────────────────────────────────────────────────

function riderIcon(rider: RiderMapData): L.DivIcon {
    const s   = STATUS[rider.status];
    const ini = getInitials(rider.name);
    const pulse = rider.status === 'on_delivery';

    return L.divIcon({
        className:   '',
        iconSize:    [48, 58],
        iconAnchor:  [24, 50],
        popupAnchor: [0, -52],
        html: `
<div style="position:relative;width:48px;height:58px;display:flex;flex-direction:column;align-items:center;gap:2px;">
  ${pulse ? `<div style="position:absolute;top:0;left:0;width:48px;height:48px;border-radius:50%;background:${s.ring};animation:riderPulse 2.2s ease-in-out infinite;"></div>` : ''}
  <div style="
    position:relative;z-index:1;width:44px;height:44px;border-radius:50%;
    background:${s.hex};display:flex;align-items:center;justify-content:center;
    font:700 13px/1 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
    color:#fff;border:2.5px solid #fff;
    box-shadow:0 3px 12px rgba(0,0,0,.22);
    ${s.dimmed ? 'opacity:.45;' : ''}
  ">${ini}</div>
  ${rider.orderId ? `<div style="
    position:relative;z-index:1;
    background:#1e293b;color:#f1f5f9;border-radius:5px;padding:1px 6px;
    font:600 9px/14px -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
    letter-spacing:.02em;white-space:nowrap;
    box-shadow:0 1px 4px rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.1);
  ">${rider.orderId}</div>` : ''}
</div>`,
    });
}

function depotIcon(): L.DivIcon {
    return L.divIcon({
        className:   '',
        iconSize:    [42, 42],
        iconAnchor:  [21, 21],
        popupAnchor: [0, -22],
        html: `
<div style="
  width:42px;height:42px;border-radius:11px;
  background:linear-gradient(135deg,#f97316,#ea580c);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 3px 14px rgba(249,115,22,.40);border:2.5px solid #fff;
">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
       stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 2a7 7 0 0 1 7 7c0 5-7 13-7 13S5 14 5 9a7 7 0 0 1 7-7z"/>
    <circle cx="12" cy="9" r="2.5" fill="#fff" stroke="none"/>
  </svg>
</div>`,
    });
}

function destIcon(hex: string): L.DivIcon {
    return L.divIcon({
        className:  '',
        iconSize:   [18, 18],
        iconAnchor: [9, 9],
        html: `<div style="width:14px;height:14px;border-radius:50%;background:${hex};border:2.5px solid #fff;box-shadow:0 1px 5px rgba(0,0,0,.22);"></div>`,
    });
}

// ─── Inner map controller (needs to be inside <MapContainer>) ─────────────────

function MapController({ onMount }: { onMount: (m: L.Map) => void }) {
    const map = useMap();
    useEffect(() => {
        onMount(map);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps
    return null;
}

// ─── Main component ───────────────────────────────────────────────────────────

interface Props {
    /** Override demo data with real rider positions from the server. */
    riders?: RiderMapData[];
}

export default function RiderMap({ riders: externalRiders }: Props) {
    const [riders, setRiders]         = useState<RiderMapData[]>(externalRiders ?? DEMO_RIDERS);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [lastTick, setLastTick]     = useState<Date>(new Date());
    const mapRef     = useRef<L.Map | null>(null);
    const progressRef = useRef<Record<number, number>>({
        1: 0.22, // Brian — already partway along route
        2: 0.18, // Kevin — just started
    });

    // ── CSS ────────────────────────────────────────────────────────────────────
    useEffect(() => { ensureMapStyles(); }, []);

    // ── Simulation (advances active riders along routes every 2 s) ─────────────
    useEffect(() => {
        const id = setInterval(() => {
            setRiders(prev => prev.map(r => {
                if (r.status !== 'on_delivery' || !r.route) return r;
                const next = ((progressRef.current[r.id] ?? 0) + 0.013) % 1;
                progressRef.current[r.id] = next;
                const [lat, lng] = interpolate(r.route, next);

                // Compute heading from direction of travel
                const prev2 = interpolate(r.route, Math.max(0, next - 0.01));
                const dLat  = lat  - prev2[0];
                const dLng  = lng  - prev2[1];
                const heading = Math.round((Math.atan2(dLng, dLat) * 180) / Math.PI + 360) % 360;

                // Update location label based on progress
                const labels = r.route === ROUTE_LANGAS
                    ? ['Depot', 'Town', 'Kimumu', 'Langas Rd', 'Langas Rd', 'Langas', 'Langas']
                    : r.route === ROUTE_HURUMA
                        ? ['Depot', 'Town', 'Huruma Rd', 'Huruma', 'Huruma', 'Huruma Est', 'Huruma Est']
                        : ['Depot', 'Town', 'Kapseret Rd', 'Kapseret', 'Kapseret', 'Kapseret Est', 'Kapseret'];
                const labelIdx = Math.min(Math.floor(next * labels.length), labels.length - 1);

                return { ...r, lat, lng, heading, location: labels[labelIdx] };
            }));
            setLastTick(new Date());
        }, 2000);
        return () => clearInterval(id);
    }, []);

    // ── Laravel Echo subscription ──────────────────────────────────────────────
    useEffect(() => {
        const w = window as any;
        if (!w.Echo) return;
        const ch = w.Echo.private('admin.riders');
        ch.listen('.rider.location.updated', (data: {
            riderId:  number;
            lat:      number;
            lng:      number;
            status:   RiderStatus;
            heading:  number | null;
            orderId:  string | null;
            location: string | null;
        }) => {
            setRiders(prev => prev.map(r =>
                r.id === data.riderId
                    ? {
                        ...r,
                        lat:      data.lat,
                        lng:      data.lng,
                        status:   data.status,
                        heading:  data.heading  ?? r.heading,
                        orderId:  data.orderId  ?? r.orderId,
                        location: data.location ?? r.location,
                    }
                    : r,
            ));
            setLastTick(new Date());
        });
        return () => { w.Echo.leave('admin.riders'); };
    }, []);

    // ── Controls ───────────────────────────────────────────────────────────────
    const fitAll = useCallback(() => {
        if (!mapRef.current) return;
        const pts = riders
            .filter(r => r.status !== 'offline')
            .map(r => [r.lat, r.lng] as [number, number]);
        if (pts.length) {
            mapRef.current.fitBounds(L.latLngBounds(pts), { padding: [55, 55], maxZoom: 15, animate: true });
        }
    }, [riders]);

    const focusRider = useCallback((r: RiderMapData) => {
        setSelectedId(r.id);
        mapRef.current?.flyTo([r.lat, r.lng], 15, { duration: 0.9 });
    }, []);

    // ── Derived stats ──────────────────────────────────────────────────────────
    const activeCount    = riders.filter(r => r.status === 'on_delivery').length;
    const availableCount = riders.filter(r => r.status === 'available').length;
    const offlineCount   = riders.filter(r => r.status === 'offline').length;
    const timeStr        = lastTick.toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    return (
        <div className="flex flex-col rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">

            {/* ── Header ──────────────────────────────────────────────────── */}
            <div className="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50">
                        <Navigation className="h-4 w-4 text-orange-500" />
                    </div>
                    <div className="leading-none">
                        <h2 className="text-sm font-semibold text-slate-900">Live Operations Map</h2>
                        <p className="text-xs text-slate-400 mt-0.5">
                            <span className="text-amber-500 font-medium">{activeCount}</span> on delivery
                            {' · '}
                            <span className="text-emerald-500 font-medium">{availableCount}</span> available
                            {' · '}
                            <span className="text-slate-400">{offlineCount}</span> offline
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    {/* Live pulse */}
                    <div className="flex items-center gap-1.5">
                        <span className="relative flex h-2 w-2">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70" />
                            <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                        </span>
                        <span className="text-xs font-semibold text-emerald-600">Live</span>
                        <span className="text-slate-300 text-xs">·</span>
                        <span className="text-xs text-slate-400 tabular-nums">{timeStr}</span>
                    </div>

                    {/* Fit all */}
                    <button
                        onClick={fitAll}
                        title="Zoom to fit all active riders"
                        className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-all"
                    >
                        <Maximize2 className="h-3.5 w-3.5" />
                        Fit all
                    </button>
                </div>
            </div>

            {/* ── Map + side panel ─────────────────────────────────────────── */}
            <div className="flex" style={{ height: 440 }}>

                {/* Map area */}
                <div className="relative flex-1 min-w-0">
                    <MapContainer
                        center={ELDORET_CENTER}
                        zoom={13}
                        style={{ height: '100%', width: '100%' }}
                        zoomControl
                        attributionControl
                    >
                        {/* CartoDB Positron — clean, minimal, no API key required */}
                        <TileLayer
                            url="https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png"
                            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
                            subdomains="abcd"
                            maxZoom={19}
                        />

                        <MapController onMount={m => { mapRef.current = m; }} />

                        {/* ── Depot ───────────────────────────────────────── */}
                        <Marker position={DEPOT} icon={depotIcon()}>
                            <Popup className="rider-popup">
                                <div className="px-3.5 py-3 min-w-[150px]">
                                    <p className="text-xs font-bold text-slate-900">EldoGas Depot</p>
                                    <p className="text-[11px] text-slate-500 mt-0.5">Main distribution hub · Eldoret</p>
                                </div>
                            </Popup>
                        </Marker>

                        {/* ── Riders ──────────────────────────────────────── */}
                        {riders.map(rider =>
                            rider.status !== 'offline' ? (
                                <Marker
                                    key={rider.id}
                                    position={[rider.lat, rider.lng]}
                                    icon={riderIcon(rider)}
                                    eventHandlers={{ click: () => setSelectedId(rider.id) }}
                                >
                                    <Popup className="rider-popup">
                                        <div className="px-3.5 py-3 min-w-[175px]">
                                            {/* Avatar + name */}
                                            <div className="flex items-center gap-2.5 mb-2.5">
                                                <div
                                                    className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white font-bold text-xs"
                                                    style={{ background: STATUS[rider.status].hex }}
                                                >
                                                    {getInitials(rider.name)}
                                                </div>
                                                <div>
                                                    <p className="text-xs font-semibold text-slate-900 leading-none">{rider.name}</p>
                                                    <p className="text-[10px] text-slate-400 mt-0.5 flex items-center gap-0.5">
                                                        <MapPin className="h-2.5 w-2.5" />{rider.location}
                                                    </p>
                                                </div>
                                            </div>
                                            {/* Status + order */}
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="rounded-full px-2 py-0.5 text-[10px] font-semibold text-white"
                                                    style={{ background: STATUS[rider.status].hex }}
                                                >
                                                    {STATUS[rider.status].label}
                                                </span>
                                                {rider.orderId && (
                                                    <span className="font-mono text-[10px] font-semibold text-slate-500 bg-slate-100 rounded px-1.5 py-0.5">
                                                        {rider.orderId}
                                                    </span>
                                                )}
                                            </div>
                                            {/* Heading */}
                                            {rider.status === 'on_delivery' && (
                                                <p className="mt-1.5 text-[10px] text-slate-400">
                                                    Heading: {rider.heading}°
                                                </p>
                                            )}
                                        </div>
                                    </Popup>
                                </Marker>
                            ) : null,
                        )}

                        {/* ── Routes ──────────────────────────────────────── */}
                        {riders.map(rider => {
                            if (rider.status !== 'on_delivery' || !rider.route || !rider.destination) return null;

                            const prog      = progressRef.current[rider.id] ?? 0;
                            const segCount  = rider.route.length - 1;
                            const rawSeg    = prog * segCount;
                            const segIdx    = Math.min(Math.floor(rawSeg), segCount - 1);
                            const hex       = STATUS.on_delivery.hex;

                            // Traveled — all waypoints before current + current pos
                            const traveled: [number, number][] = [
                                ...rider.route.slice(0, segIdx + 1),
                                [rider.lat, rider.lng],
                            ];

                            // Remaining — current pos + waypoints ahead
                            const remaining: [number, number][] = [
                                [rider.lat, rider.lng],
                                ...rider.route.slice(segIdx + 1),
                            ];

                            return (
                                <React.Fragment key={`route-${rider.id}`}>
                                    {/* Traveled path */}
                                    <Polyline
                                        positions={traveled}
                                        pathOptions={{ color: hex, weight: 3, opacity: 0.20 }}
                                    />
                                    {/* Remaining path — dashed */}
                                    <Polyline
                                        positions={remaining}
                                        pathOptions={{ color: hex, weight: 2.5, opacity: 0.65, dashArray: '7 6' }}
                                    />
                                    {/* Destination dot */}
                                    <Marker position={rider.destination} icon={destIcon(hex)} />
                                </React.Fragment>
                            );
                        })}
                    </MapContainer>

                    {/* Eldoret label overlay */}
                    <div className="pointer-events-none absolute bottom-8 left-3 z-[400]">
                        <div className="rounded-md border border-orange-100 bg-white/80 px-2.5 py-1.5 backdrop-blur-sm">
                            <p className="text-[10px] font-bold text-slate-700 leading-none">Eldoret, Kenya</p>
                            <p className="text-[9px] text-slate-400 mt-0.5">Demo simulation active</p>
                        </div>
                    </div>
                </div>

                {/* ── Rider side panel ────────────────────────────────────── */}
                <div className="flex w-52 shrink-0 flex-col border-l border-slate-100 overflow-hidden">

                    {/* Panel header */}
                    <div className="bg-slate-50 px-3.5 py-2.5 border-b border-slate-100">
                        <p className="text-[10px] font-semibold uppercase tracking-widest text-slate-500">
                            Riders · {riders.length}
                        </p>
                    </div>

                    {/* Rider list */}
                    <div className="flex-1 overflow-y-auto divide-y divide-slate-50">
                        {riders.map(rider => {
                            const s          = STATUS[rider.status];
                            const isSelected = rider.id === selectedId;

                            return (
                                <button
                                    key={rider.id}
                                    onClick={() => focusRider(rider)}
                                    disabled={rider.status === 'offline'}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left transition-colors',
                                        rider.status !== 'offline' && 'hover:bg-orange-50/50 cursor-pointer',
                                        rider.status === 'offline' && 'cursor-default opacity-50',
                                        isSelected && 'bg-orange-50',
                                    )}
                                >
                                    {/* Avatar */}
                                    <div
                                        className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white font-bold text-[11px]"
                                        style={{ background: s.hex }}
                                    >
                                        {getInitials(rider.name)}
                                    </div>

                                    {/* Info */}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-medium leading-none text-slate-800">
                                            {rider.name}
                                        </p>
                                        <div className="mt-0.5 flex items-center gap-1">
                                            <span
                                                className="h-1.5 w-1.5 shrink-0 rounded-full"
                                                style={{ background: s.hex }}
                                            />
                                            <span className="truncate text-[10px] text-slate-500">
                                                {rider.orderId ?? s.label}
                                            </span>
                                        </div>
                                        {rider.status !== 'offline' && (
                                            <p className="mt-0.5 flex items-center gap-0.5 text-[10px] text-slate-400 truncate">
                                                <MapPin className="h-2.5 w-2.5 shrink-0" />
                                                {rider.location}
                                            </p>
                                        )}
                                    </div>

                                    {/* Active badge */}
                                    {rider.status === 'on_delivery' && (
                                        <span className="shrink-0 h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse" />
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {/* Legend */}
                    <div className="border-t border-slate-100 bg-slate-50 px-3.5 py-3 space-y-1.5">
                        {(Object.entries(STATUS) as [RiderStatus, StatusDef][]).map(([k, v]) => (
                            <div key={k} className="flex items-center gap-2">
                                <span className="h-2 w-2 shrink-0 rounded-full" style={{ background: v.hex }} />
                                <span className="text-[10px] text-slate-500">{v.label}</span>
                            </div>
                        ))}
                        <div className="flex items-center gap-2 pt-1 border-t border-slate-100">
                            <span className="inline-block h-px w-4 border-t-2 border-dashed border-orange-400 shrink-0" />
                            <span className="text-[10px] text-slate-400">Planned route</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
