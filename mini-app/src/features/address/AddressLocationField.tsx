import { useEffect, useRef, useState } from 'react';
import { Loader2, LocateFixed, Search } from 'lucide-react';
import LazyMapPicker from '../../components/map/LazyMapPicker';
import { roundCoordinate } from '../../components/map/geo';
import { hapticFeedback } from '../../lib/telegram';
import { reverseGeocode, searchAddress } from './geocodeApi';
import { GeolocationError, getCurrentPosition } from './geolocation';
import type { GeolocationFailure } from './geolocation';
import type { AddressLocationValue, GeocodedPlace, LatLng } from './types';

const MIN_SEARCH_LENGTH = 3;
// After the last tap/drag, so a few corrections in a row cost one lookup, not several
const REVERSE_DEBOUNCE_MS = 500;

const GEOLOCATION_MESSAGES: Record<GeolocationFailure, string> = {
  unsupported: "Qurilmangiz joylashuvni aniqlay olmaydi. Xaritada belgilang yoki manzilni qidiring.",
  denied: "Joylashuvga ruxsat berilmagan. Telegram yoki qurilma sozlamalaridan ruxsat bering yoki xaritada belgilang.",
  unavailable: "Joylashuvni aniqlab bo'lmadi. Xaritada qo'lda belgilang.",
  timeout: "Joylashuvni aniqlash uzoq davom etdi. Qayta urinib ko'ring yoki xaritada belgilang.",
};

interface Notice {
  tone: 'info' | 'warn';
  text: string;
}

interface AddressLocationFieldProps {
  value: AddressLocationValue;
  /** Gets only what changed. Merge it into the latest state: `setValue((prev) => ({ ...prev, ...patch }))`. */
  onChange: (patch: Partial<AddressLocationValue>) => void;
  /** Where the map opens until a pin exists (the restaurant, say). It only moves the view: it is never saved. */
  fallbackCenter?: LatLng | null;
  /** Marks the address text as required for the surrounding <form>. */
  required?: boolean;
  mapHeight?: number;
}

/**
 * The "where do we deliver" part of an address form: find yourself ("Joylashuvimni aniqlash"), look an address up,
 * or drop/drag a pin. The pin is the source of truth (the courier and the delivery zone use its coordinates); the
 * address text is what the pin resolves to, via the backend, and can be edited.
 *
 * Coordinates stay null until a real point is picked.
 */
export default function AddressLocationField({ value, onChange, fallbackCenter = null, required = false, mapHeight = 220 }: AddressLocationFieldProps) {
  const [isLocating, setIsLocating] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [isResolving, setIsResolving] = useState(false);
  const [results, setResults] = useState<GeocodedPlace[] | null>(null);
  // What the pin resolves to, offered (not applied) when the customer has typed an address of their own
  const [suggestion, setSuggestion] = useState<string | null>(null);
  const [notice, setNotice] = useState<Notice | null>(null);

  // The customer's own text must not be overwritten when the pin moves
  const typedRef = useRef(value.address.trim() !== '');
  const reverseAbortRef = useRef<AbortController | null>(null);
  const searchAbortRef = useRef<AbortController | null>(null);
  const reverseTimerRef = useRef<number | null>(null);

  useEffect(() => () => {
    reverseAbortRef.current?.abort();
    searchAbortRef.current?.abort();
    if (reverseTimerRef.current !== null) window.clearTimeout(reverseTimerRef.current);
  }, []);

  const position = value.lat !== null && value.lng !== null ? { lat: value.lat, lng: value.lng } : null;

  const cancelReverse = () => {
    if (reverseTimerRef.current !== null) window.clearTimeout(reverseTimerRef.current);
    reverseTimerRef.current = null;
    reverseAbortRef.current?.abort();
  };

  const resolveAddress = async (point: LatLng) => {
    cancelReverse();
    const controller = new AbortController();
    reverseAbortRef.current = controller;
    setIsResolving(true);

    try {
      const place = await reverseGeocode(point.lat, point.lng, controller.signal);
      if (controller.signal.aborted) return;
      if (!place) {
        setNotice({ tone: 'warn', text: "Bu nuqta uchun manzil topilmadi. Manzilni o'zingiz yozing: joylashuv saqlanadi." });
      } else if (typedRef.current) {
        setSuggestion(place.address);
      } else {
        onChange({ address: place.address });
      }
    } catch {
      if (controller.signal.aborted) return;
      setNotice({ tone: 'warn', text: "Manzil matnini aniqlab bo'lmadi. Uni o'zingiz yozing: joylashuv saqlanadi." });
    } finally {
      if (reverseAbortRef.current === controller) setIsResolving(false);
    }
  };

  // The customer tapped the map or dragged the pin
  const handlePick = (point: LatLng) => {
    setNotice(null);
    setResults(null);
    setSuggestion(null);
    onChange({ lat: point.lat, lng: point.lng });

    cancelReverse();
    setIsResolving(true);
    reverseTimerRef.current = window.setTimeout(() => {
      reverseTimerRef.current = null;
      void resolveAddress(point);
    }, REVERSE_DEBOUNCE_MS);
  };

  const handleLocate = async () => {
    hapticFeedback('light');
    setIsLocating(true);
    setNotice(null);
    setResults(null);
    setSuggestion(null);

    try {
      const device = await getCurrentPosition();
      const point = { lat: roundCoordinate(device.lat), lng: roundCoordinate(device.lng) };
      onChange({ lat: point.lat, lng: point.lng });
      hapticFeedback('success');
      void resolveAddress(point);
    } catch (err) {
      hapticFeedback('error');
      const reason = err instanceof GeolocationError ? err.reason : 'unavailable';
      setNotice({ tone: 'warn', text: GEOLOCATION_MESSAGES[reason] });
    } finally {
      setIsLocating(false);
    }
  };

  // Explicit (button / Enter), not as-you-type: the geocoder's usage policy forbids autocomplete-style traffic
  const handleSearch = async () => {
    const query = value.address.trim();
    if (query.length < MIN_SEARCH_LENGTH || isSearching) return;

    hapticFeedback('light');
    searchAbortRef.current?.abort();
    const controller = new AbortController();
    searchAbortRef.current = controller;
    setIsSearching(true);
    setNotice(null);
    setSuggestion(null);

    try {
      const found = await searchAddress(query, controller.signal);
      if (controller.signal.aborted) return;
      setResults(found);
      if (found.length === 0) {
        setNotice({ tone: 'info', text: "Hech narsa topilmadi. Manzilni boshqacha yozib ko'ring yoki xaritada belgilang." });
      }
    } catch {
      if (controller.signal.aborted) return;
      setResults(null);
      setNotice({ tone: 'warn', text: "Manzilni qidirib bo'lmadi. Qayta urinib ko'ring yoki xaritada belgilang." });
    } finally {
      if (searchAbortRef.current === controller) setIsSearching(false);
    }
  };

  const handleSelectResult = (place: GeocodedPlace) => {
    hapticFeedback('light');
    cancelReverse(); // the address of the pin it replaces no longer applies
    setIsResolving(false);
    typedRef.current = false;
    setResults(null);
    setSuggestion(null);
    setNotice(null);
    onChange({ address: place.address, lat: roundCoordinate(place.lat), lng: roundCoordinate(place.lng) });
  };

  const handleApplySuggestion = () => {
    if (suggestion === null) return;
    hapticFeedback('light');
    typedRef.current = false;
    onChange({ address: suggestion });
    setSuggestion(null);
  };

  const canSearch = value.address.trim().length >= MIN_SEARCH_LENGTH && !isSearching;

  return (
    <div className="flex flex-col gap-3">
      <button
        type="button"
        onClick={handleLocate}
        disabled={isLocating}
        className="w-full py-3 bg-amber-500/10 text-amber-600 rounded-xl text-xs font-black flex items-center justify-center gap-2 border border-amber-500/20 hover:bg-amber-500/20 active:scale-95 transition-all disabled:opacity-50"
      >
        {isLocating ? <Loader2 className="w-4 h-4 animate-spin" /> : <LocateFixed className="w-4 h-4" />}
        <span>{isLocating ? 'Aniqlanmoqda...' : 'Joylashuvimni aniqlash'}</span>
      </button>

      <div className="flex gap-2">
        <input
          type="text"
          required={required}
          value={value.address}
          enterKeyHint="search"
          aria-label="Manzil"
          placeholder="Ko'cha va uy raqami (masalan: Chilonzor 12)"
          onChange={(event) => {
            typedRef.current = event.target.value.trim() !== '';
            setResults(null);
            setSuggestion(null);
            setNotice(null);
            onChange({ address: event.target.value });
          }}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              event.preventDefault();
              void handleSearch();
            }
          }}
          className="min-w-0 flex-1 select-text bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs outline-none focus:border-amber-500 transition-colors font-semibold"
        />
        <button
          type="button"
          onClick={handleSearch}
          disabled={!canSearch}
          aria-label="Manzilni qidirish"
          className="w-11 shrink-0 flex items-center justify-center bg-slate-800 text-white rounded-xl active:scale-95 transition disabled:opacity-30"
        >
          {isSearching ? <Loader2 className="w-4 h-4 animate-spin" /> : <Search className="w-4 h-4" />}
        </button>
      </div>

      {results !== null && results.length > 0 && (
        <ul className="bg-white border border-slate-200 rounded-xl divide-y divide-slate-100 overflow-hidden" aria-label="Topilgan manzillar">
          {results.map((place) => (
            <li key={`${place.lat},${place.lng},${place.address}`}>
              <button
                type="button"
                onClick={() => handleSelectResult(place)}
                className="w-full text-left px-3 py-2.5 text-[11px] font-semibold text-slate-700 hover:bg-amber-50 active:bg-amber-50 transition"
              >
                📍 {place.address}
              </button>
            </li>
          ))}
        </ul>
      )}

      {suggestion !== null && (
        <button
          type="button"
          onClick={handleApplySuggestion}
          className="w-full text-left px-3 py-2.5 rounded-xl border border-amber-200 bg-amber-50 text-[10px] font-semibold text-amber-800 active:scale-[0.99] transition"
        >
          <span className="block font-black uppercase tracking-wider text-[9px] text-amber-600">Xarita bo'yicha manzil (bosing va qo'llang)</span>
          {suggestion}
        </button>
      )}

      <LazyMapPicker position={position} onPick={handlePick} fallbackCenter={fallbackCenter} height={mapHeight} />

      <div role="status" className="flex flex-col gap-1">
        {isResolving && (
          <p className="text-[10px] font-bold text-slate-400 flex items-center gap-1.5">
            <Loader2 className="w-3 h-3 animate-spin" /> Manzil aniqlanmoqda...
          </p>
        )}
        {notice && (
          <p className={`text-[10px] font-bold ${notice.tone === 'warn' ? 'text-rose-500' : 'text-slate-500'}`}>{notice.text}</p>
        )}
        {position ? (
          <p className="text-[10px] font-bold text-emerald-600">✓ Joylashuv belgilandi. Aniqroq joylashtirish uchun pinni sudrang.</p>
        ) : (
          <p className="text-[10px] font-bold text-amber-600">Joylashuv hali belgilanmagan: xaritani bosing, joylashuvingizni aniqlang yoki manzilni qidiring.</p>
        )}
      </div>
    </div>
  );
}
