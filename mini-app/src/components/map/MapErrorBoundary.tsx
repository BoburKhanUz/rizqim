import { Component } from 'react';
import type { ReactNode } from 'react';
import { Button } from '../ui';

interface MapErrorBoundaryProps {
  children: ReactNode;
  height: number;
}

interface MapErrorBoundaryState {
  failed: boolean;
}

// The map is a lazily loaded chunk that can fail on a flaky connection. Without this, that failure would take down the
// whole page (the cart); with it, only the map area is replaced and the rest of the form keeps working.
export default class MapErrorBoundary extends Component<MapErrorBoundaryProps, MapErrorBoundaryState> {
  state: MapErrorBoundaryState = { failed: false };

  static getDerivedStateFromError(): MapErrorBoundaryState {
    return { failed: true };
  }

  render() {
    if (!this.state.failed) return this.props.children;

    return (
      <div
        className="flex flex-col items-center justify-center gap-2 rounded-card bg-danger-soft px-4 text-center"
        style={{ height: this.props.height }}
        role="alert"
      >
        <p className="text-footnote font-bold text-danger-soft-fg">Xaritani yuklab bo'lmadi.</p>
        <p className="text-caption text-danger-soft-fg">
          Joylashuvni "Joylashuvimni aniqlash" tugmasi yoki manzilni qidirish orqali belgilashingiz mumkin.
        </p>
        <Button variant="secondary" onClick={() => window.location.reload()}>
          Sahifani yangilash
        </Button>
      </div>
    );
  }
}
