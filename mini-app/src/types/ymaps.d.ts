// The slice of the Yandex Maps 2.1 JS API (loaded from api-maps.yandex.ru at runtime) that the app uses.
export interface YandexMapEvent {
  get(name: 'coords'): [number, number];
}

export interface YandexPlacemark {
  geometry: {
    getCoordinates(): number[];
    setCoordinates(coords: number[]): void;
  };
  events: { add(type: 'dragend', handler: () => void): void };
}

export interface YandexMap {
  geoObjects: { add(object: YandexPlacemark): void };
  events: { add(type: 'click', handler: (event: YandexMapEvent) => void): void };
  setCenter(coords: number[], zoom?: number): void;
  destroy(): void;
}

export interface YandexGeocodeResult {
  geoObjects: { get(index: number): { getAddressLine(): string } | undefined };
}

export interface YandexMaps {
  ready(callback: () => void): void;
  geocode(coords: number[]): Promise<YandexGeocodeResult>;
  Map: new (container: HTMLElement, state: { center: number[]; zoom: number; controls?: string[] }) => YandexMap;
  Placemark: new (
    coords: number[],
    properties: Record<string, unknown>,
    options: { preset?: string; draggable?: boolean },
  ) => YandexPlacemark;
}

declare global {
  interface Window {
    ymaps?: YandexMaps;
  }
}
