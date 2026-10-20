// Query parameters that narrow GET /catalog and GET /stores to the places the customer can be served at.
export interface LocationParams {
  lat?: number;
  lng?: number;
  region_id?: number;
  district_id?: number;
}

interface AddressLocation {
  lat?: number | null;
  lng?: number | null;
  region_id?: number | null;
  district_id?: number | null;
}

// Only the parts of the address that are set (and not zero) are sent.
export function getLocationParams(address: AddressLocation | null | undefined): LocationParams {
  const params: LocationParams = {};
  if (address) {
    if (address.lat) params.lat = address.lat;
    if (address.lng) params.lng = address.lng;
    if (address.region_id) params.region_id = address.region_id;
    if (address.district_id) params.district_id = address.district_id;
  }
  return params;
}
