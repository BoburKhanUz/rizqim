import { lazy, Suspense } from 'react';
import { Spinner } from '../ui';
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
            role="status"
            className="flex items-center justify-center gap-2 rounded-card border border-line bg-surface-2 text-footnote text-fg-muted"
            style={{ height }}
          >
            <Spinner className="size-4" />
            Xarita yuklanmoqda...
          </div>
        }
      >
        <MapPicker {...props} height={height} />
      </Suspense>
    </MapErrorBoundary>
  );
}
