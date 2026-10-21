// Regions and districts of the address forms (GET /locations). The superadmin store form's own types are @rizqim/staff's.
export interface Region {
  id: number;
  name: string;
  is_active?: boolean; // only on GET /locations
}

export interface District extends Region {
  region_id: number;
}

