import type { LatLng } from '../../components/map/types';

export type { LatLng };

// What /geocode/reverse and /geocode/search return for one place
export interface GeocodedPlace {
  address: string;
  lat: number;
  lng: number;
}

// The location half of an address form: the address text and, once the customer has picked a spot, the pin.
// lat/lng stay null until a real point is known: there is no default and no fallback.
export interface AddressLocationValue {
  address: string;
  lat: number | null;
  lng: number | null;
}
