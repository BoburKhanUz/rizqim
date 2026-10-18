import { Component } from 'react';
import type { ReactNode } from 'react';

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
        className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-rose-100 bg-rose-50 px-4 text-center"
        style={{ height: this.props.height }}
        role="alert"
      >
        <p className="text-[11px] font-bold text-rose-600">Xaritani yuklab bo'lmadi.</p>
        <p className="text-[10px] font-semibold text-rose-500">
          Joylashuvni "Joylashuvimni aniqlash" tugmasi yoki manzilni qidirish orqali belgilashingiz mumkin.
        </p>
        <button
          type="button"
          onClick={() => window.location.reload()}
          className="rounded-xl border border-rose-200 bg-white px-3 py-1.5 text-[10px] font-black text-rose-600 active:scale-95 transition"
        >
          Sahifani yangilash
        </button>
      </div>
    );
  }
}
