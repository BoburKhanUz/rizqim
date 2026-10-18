import { lazy, Suspense } from 'react';
import MapErrorBoundary from './MapErrorBoundary';
import type { MapPickerProps } from './MapPicker';

// Leaflet and its CSS are big and only needed once someone opens an address form: keep them out of the first bundle
const MapPicker = lazy(() => import('./MapPicker'));

const DEFAULT_HEIGHT = 220;

export default function LazyMapPicker(props: MapPickerProps) {
  const height = props.height ?? DEFAULT_HEIGHT;

  return (
    <MapErrorBoundary height={height}>
      <Suspense
        fallback={
          <div
            className="flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-[11px] font-bold text-slate-400"
            style={{ height }}
          >
            Xarita yuklanmoqda...
          </div>
        }
      >
        <MapPicker {...props} height={height} />
      </Suspense>
    </MapErrorBoundary>
  );
}
