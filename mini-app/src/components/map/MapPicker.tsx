import 'leaflet/dist/leaflet.css';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { MouseEvent as ReactMouseEvent, RefObject } from 'react';
import type { LatLngTuple, LeafletEvent, LeafletMouseEvent, Marker as LeafletMarker } from 'leaflet';
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet';
import { tg } from '../../lib/telegram';
import { roundCoordinate } from './geo';
import { AREA_ZOOM, CITY_ZOOM, DEFAULT_MAP_CENTER, MAP_ATTRIBUTION, MAP_MAX_ZOOM, MAP_TILE_URL, PIN_ZOOM } from './mapConfig';
import { pinIcon } from './pinIcon';
import type { LatLng } from './types';

export interface MapPickerProps {
  /** The picked point; null until a pin is dropped. */
  position: LatLng | null;
  /** The user tapped the map or dragged the pin. Not called when `position` is changed from outside (search, GPS). */
  onPick: (position: LatLng) => void;
  /** Where the map opens while there is no pin yet (the restaurant, say). Moves the view only, never a value. */
  fallbackCenter?: LatLng | null;
  /** Height in px. */
  height?: number;
}

// Tap anywhere to drop the pin there
function PickOnTap({ onPick }: { onPick: (point: LatLng) => void }) {
  useMapEvents({
    click(event: LeafletMouseEvent) {
      onPick(event.latlng);
    },
  });
  return null;
}

// Brings a pin that was set from outside (GPS, a search result) into view, zoomed in enough to judge it.
// A point the user just picked on this very map is already in view and left alone.
function FollowPosition({ lat, lng, lastPickRef }: { lat: number | null; lng: number | null; lastPickRef: RefObject<LatLng | null> }) {
  const map = useMap();

  useEffect(() => {
    if (lat === null || lng === null) return;
    const picked = lastPickRef.current;
    if (picked && picked.lat === lat && picked.lng === lng) return;
    map.setView([lat, lng], Math.max(map.getZoom(), PIN_ZOOM));
  }, [map, lat, lng, lastPickRef]);

  return null;
}

// Leaflet only measures its container once: tell it when the layout changes (keyboard, rotation, form growing)
function KeepSized() {
  const map = useMap();

  useEffect(() => {
    if (typeof ResizeObserver === 'undefined') return;
    const observer = new ResizeObserver(() => map.invalidateSize());
    observer.observe(map.getContainer());
    return () => observer.disconnect();
  }, [map]);

  return null;
}

// A plain link would navigate the Mini App's own WebView away from the app. Only the attribution has links that go
// anywhere: every other <a> in the map is Leaflet's own (the zoom +/- buttons are <a href="#">) and must be left alone.
function openAttributionLink(event: ReactMouseEvent<HTMLDivElement>) {
  if (!(event.target instanceof Element)) return;
  const link = event.target.closest('a');
  if (!link || !link.closest('.leaflet-control-attribution')) return;
  event.preventDefault();
  if (typeof tg.openLink === 'function') {
    tg.openLink(link.href);
  } else {
    window.open(link.href, '_blank', 'noopener');
  }
}

export default function MapPicker({ position, onPick, fallbackCenter = null, height = 220 }: MapPickerProps) {
  // MapContainer reads these once, on mount; FollowPosition moves the view afterwards
  const [initialView] = useState(() => ({
    center: position ?? fallbackCenter ?? DEFAULT_MAP_CENTER,
    zoom: position ? PIN_ZOOM : fallbackCenter ? AREA_ZOOM : CITY_ZOOM,
  }));

  const lastPickRef = useRef<LatLng | null>(null);
  const handlePick = useCallback((point: LatLng) => {
    const picked = { lat: roundCoordinate(point.lat), lng: roundCoordinate(point.lng) };
    lastPickRef.current = picked;
    onPick(picked);
  }, [onPick]);

  const lat = position?.lat ?? null;
  const lng = position?.lng ?? null;
  const markerPosition = useMemo<LatLngTuple | null>(() => (lat !== null && lng !== null ? [lat, lng] : null), [lat, lng]);
  const markerHandlers = useMemo(() => ({
    dragend(event: LeafletEvent) {
      handlePick((event.target as LeafletMarker).getLatLng());
    },
  }), [handlePick]);

  return (
    <div
      className="relative isolate overflow-hidden rounded-2xl border border-slate-200 bg-slate-100"
      style={{ height }}
      onClickCapture={openAttributionLink}
    >
      <MapContainer
        center={[initialView.center.lat, initialView.center.lng]}
        zoom={initialView.zoom}
        maxZoom={MAP_MAX_ZOOM}
        scrollWheelZoom={false}
        className="h-full w-full"
      >
        <TileLayer url={MAP_TILE_URL} attribution={MAP_ATTRIBUTION} maxZoom={MAP_MAX_ZOOM} />
        <PickOnTap onPick={handlePick} />
        <FollowPosition lat={lat} lng={lng} lastPickRef={lastPickRef} />
        <KeepSized />
        {markerPosition && <Marker position={markerPosition} icon={pinIcon} draggable eventHandlers={markerHandlers} />}
      </MapContainer>
    </div>
  );
}
