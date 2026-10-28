// Regions and districts of the address forms (GET /locations).
export interface Region {
  id: number;
  name: string;
  is_active?: boolean; // only on GET /locations
}

export interface District extends Region {
  region_id: number;
}

