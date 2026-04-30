import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, useCallback } from 'react';
import { MapPin, Crosshair, Loader2, CheckCircle, Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import GuestLayout from '@/Layouts/GuestLayout';
import CustomerLayout from '@/Layouts/CustomerLayout';

const DEFAULT_LAT = 0.5143;
const DEFAULT_LNG = 35.2698;

const LABELS = ['Home', 'Office', 'Restaurant', 'Other'] as const;
type AddressLabel = typeof LABELS[number];

interface Suggestion {
    place_id:    number;
    display_name: string;
    lat:         string;
    lon:         string;
}

interface Props {
    isOnboarding: boolean;
    redirect_to?: string;
}

export default function SetLocation({ isOnboarding, redirect_to = '' }: Props) {
    const { errors } = usePage().props as any;
    const mapRef     = useRef<HTMLDivElement>(null);
    const leafletMap = useRef<any>(null);
    const marker     = useRef<any>(null);
    const searchRef  = useRef<HTMLDivElement>(null);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [lat,          setLat]          = useState(DEFAULT_LAT);
    const [lng,          setLng]          = useState(DEFAULT_LNG);
    const [label,        setLabel]        = useState<AddressLabel>('Home');
    const [description,  setDescription]  = useState('');
    const [loading,      setLoading]      = useState(false);
    const [locating,     setLocating]     = useState(false);

    // Search state
    const [query,        setQuery]        = useState('');
    const [suggestions,  setSuggestions]  = useState<Suggestion[]>([]);
    const [searching,    setSearching]    = useState(false);
    const [showDrop,     setShowDrop]     = useState(false);
    const [activeIdx,    setActiveIdx]    = useState(-1);

    // Move map + marker to a lat/lng
    const flyTo = useCallback((newLat: number, newLng: number, zoom = 17) => {
        setLat(newLat);
        setLng(newLng);
        leafletMap.current?.setView([newLat, newLng], zoom);
        marker.current?.setLatLng([newLat, newLng]);
    }, []);

    // Nominatim search (Kenya-biased)
    const search = useCallback(async (q: string) => {
        if (q.trim().length < 3) { setSuggestions([]); setShowDrop(false); return; }
        setSearching(true);
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?` +
                new URLSearchParams({
                    q,
                    format:         'json',
                    addressdetails: '1',
                    limit:          '6',
                    countrycodes:   'ke',
                    'accept-language': 'en',
                }),
                { headers: { 'User-Agent': 'EldoGasApp/1.0' } },
            );
            const data: Suggestion[] = await res.json();
            setSuggestions(data);
            setShowDrop(data.length > 0);
            setActiveIdx(-1);
        } catch {
            setSuggestions([]);
        } finally {
            setSearching(false);
        }
    }, []);

    // Debounce input
    function onQueryChange(val: string) {
        setQuery(val);
        if (debounceTimer.current) clearTimeout(debounceTimer.current);
        if (val.trim().length < 3) { setSuggestions([]); setShowDrop(false); return; }
        debounceTimer.current = setTimeout(() => search(val), 400);
    }

    function selectSuggestion(s: Suggestion) {
        setQuery(s.display_name.split(',').slice(0, 2).join(',').trim());
        setSuggestions([]);
        setShowDrop(false);
        flyTo(parseFloat(s.lat), parseFloat(s.lon), 17);
    }

    function clearSearch() {
        setQuery('');
        setSuggestions([]);
        setShowDrop(false);
    }

    // Keyboard navigation
    function onKeyDown(e: React.KeyboardEvent) {
        if (! showDrop) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIdx(i => Math.min(i + 1, suggestions.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIdx(i => Math.max(i - 1, -1));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0) selectSuggestion(suggestions[activeIdx]);
        } else if (e.key === 'Escape') {
            setShowDrop(false);
        }
    }

    // Close dropdown on outside click
    useEffect(() => {
        function onOutside(e: MouseEvent) {
            if (searchRef.current && ! searchRef.current.contains(e.target as Node)) {
                setShowDrop(false);
            }
        }
        document.addEventListener('mousedown', onOutside);
        return () => document.removeEventListener('mousedown', onOutside);
    }, []);

    // Leaflet init
    useEffect(() => {
        async function init() {
            const L = (await import('leaflet')).default;

            delete (L.Icon.Default.prototype as any)._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });

            if (! mapRef.current || leafletMap.current) return;

            const map = L.map(mapRef.current, { zoomControl: true }).setView([DEFAULT_LAT, DEFAULT_LNG], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
            }).addTo(map);

            const pin = L.marker([DEFAULT_LAT, DEFAULT_LNG], { draggable: true }).addTo(map);

            pin.on('dragend', () => {
                const pos = pin.getLatLng();
                setLat(pos.lat);
                setLng(pos.lng);
            });

            map.on('click', (e: any) => {
                pin.setLatLng(e.latlng);
                setLat(e.latlng.lat);
                setLng(e.latlng.lng);
            });

            leafletMap.current = map;
            marker.current     = pin;

            // Auto-detect on open — moves pin to user's real position immediately
            if (navigator.geolocation) {
                setLocating(true);
                navigator.geolocation.getCurrentPosition(
                    pos => { flyTo(pos.coords.latitude, pos.coords.longitude, 17); setLocating(false); },
                    () => setLocating(false),
                    { timeout: 8000 },
                );
            }
        }

        init();

        return () => {
            leafletMap.current?.remove();
            leafletMap.current = null;
            marker.current     = null;
        };
    }, []);

    function useMyLocation() {
        if (! navigator.geolocation) return;
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            pos => { flyTo(pos.coords.latitude, pos.coords.longitude, 17); setLocating(false); },
            () => setLocating(false),
        );
    }

    function onSubmit(e: React.FormEvent) {
        e.preventDefault();
        setLoading(true);
        const params = redirect_to ? `?redirect_to=${redirect_to}` : '';
        router.post(`/addresses${params}`, { label, latitude: lat, longitude: lng, description }, {
            onFinish: () => setLoading(false),
        });
    }

    const content = (
        <div className="flex flex-col">
            {/* Header */}
            <div className="mb-4 text-center">
                <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                    <MapPin className="h-5 w-5 text-orange-500" />
                </div>
                <h2 className="text-base font-semibold text-slate-800">Pin Your Delivery Location</h2>
                <p className="text-xs text-muted-foreground mt-0.5">Search for a place, or drag the pin on the map.</p>
            </div>

            {/* Search box */}
            <div ref={searchRef} className="relative mb-3">
                <div className={cn(
                    'flex items-center gap-2 rounded-lg border bg-white px-3 transition-all',
                    showDrop || document.activeElement === searchRef.current?.querySelector('input')
                        ? 'border-orange-400 ring-2 ring-orange-400/20'
                        : 'border-slate-200',
                )}>
                    {searching
                        ? <Loader2 className="h-4 w-4 shrink-0 animate-spin text-orange-400" />
                        : <Search className="h-4 w-4 shrink-0 text-slate-400" />
                    }
                    <input
                        type="text"
                        value={query}
                        onChange={e => onQueryChange(e.target.value)}
                        onKeyDown={onKeyDown}
                        onFocus={() => suggestions.length > 0 && setShowDrop(true)}
                        placeholder="Search for a street, estate, or landmark…"
                        className="flex-1 h-10 bg-transparent text-sm outline-none placeholder:text-slate-400"
                    />
                    {query && (
                        <button type="button" onClick={clearSearch} className="text-slate-400 hover:text-slate-600">
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>

                {/* Suggestions dropdown */}
                {showDrop && suggestions.length > 0 && (
                    <ul className="absolute z-[2000] mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg overflow-hidden">
                        {suggestions.map((s, i) => {
                            const [primary, ...rest] = s.display_name.split(', ');
                            const secondary = rest.slice(0, 3).join(', ');
                            return (
                                <li
                                    key={s.place_id}
                                    onMouseDown={() => selectSuggestion(s)}
                                    className={cn(
                                        'flex items-start gap-2.5 px-3 py-2.5 cursor-pointer transition-colors',
                                        i === activeIdx
                                            ? 'bg-orange-50'
                                            : 'hover:bg-slate-50',
                                        i > 0 && 'border-t border-slate-50',
                                    )}
                                >
                                    <MapPin className={cn('h-3.5 w-3.5 shrink-0 mt-0.5', i === activeIdx ? 'text-orange-500' : 'text-slate-400')} />
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-slate-800 truncate">{primary}</p>
                                        {secondary && (
                                            <p className="text-[11px] text-slate-400 truncate">{secondary}</p>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {/* Map */}
            <div className="relative rounded-xl overflow-hidden border border-slate-200 shadow-sm" style={{ height: 280 }}>
                <div ref={mapRef} className="h-full w-full" />
                <button
                    type="button"
                    onClick={useMyLocation}
                    disabled={locating}
                    className="absolute bottom-3 right-3 z-[1000] flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow border border-slate-200 hover:bg-slate-50 transition-colors disabled:opacity-60"
                >
                    {locating ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Crosshair className="h-3.5 w-3.5 text-orange-500" />}
                    My location
                </button>
            </div>

            {/* Coords */}
            <p className="mt-1 text-center text-[10px] text-slate-400 tabular-nums">
                {lat.toFixed(6)}, {lng.toFixed(6)}
            </p>

            {/* Form */}
            <form onSubmit={onSubmit} className="mt-4 space-y-4">
                <div>
                    <Label className="text-sm font-medium text-slate-700">Address Type</Label>
                    <div className="mt-1.5 flex gap-2">
                        {LABELS.map(l => (
                            <button
                                key={l}
                                type="button"
                                onClick={() => setLabel(l)}
                                className={cn(
                                    'flex-1 rounded-lg border px-2 py-1.5 text-xs font-medium transition-colors',
                                    label === l
                                        ? 'border-orange-400 bg-orange-50 text-orange-700'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                                )}
                            >
                                {l}
                            </button>
                        ))}
                    </div>
                </div>

                <div>
                    <Label className="text-sm font-medium text-slate-700">
                        Delivery Notes <span className="text-slate-400 font-normal">(optional)</span>
                    </Label>
                    <input
                        type="text"
                        value={description}
                        onChange={e => setDescription(e.target.value)}
                        placeholder="e.g. Blue gate, apartment 4B"
                        maxLength={255}
                        className={cn(
                            'mt-1.5 w-full h-10 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm',
                            'focus:border-orange-400 focus:ring-2 focus:ring-orange-400/20 focus:bg-white transition-all outline-none',
                            errors?.description && 'border-red-400 bg-red-50',
                        )}
                    />
                </div>

                {(errors?.latitude || errors?.longitude) && (
                    <p className="text-xs text-red-500">Please select a valid location on the map.</p>
                )}

                <Button
                    type="submit"
                    disabled={loading}
                    className="w-full bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 gap-2"
                >
                    {loading ? (
                        <><Loader2 className="h-4 w-4 animate-spin" /> Saving…</>
                    ) : (
                        <><CheckCircle className="h-4 w-4" /> Save Location</>
                    )}
                </Button>
            </form>
        </div>
    );

    if (isOnboarding) {
        return <GuestLayout>{content}</GuestLayout>;
    }

    return (
        <CustomerLayout title="Add Address" showBack backHref="/addresses">
            <div className="mx-auto max-w-lg px-4 py-5">{content}</div>
        </CustomerLayout>
    );
}
