import type { ZonePolygon } from '@rizqim/staff';

// Geometry of a delivery zone as the map editor works with it. GeoJSON (what the API takes and gives) keeps [longitude,
// latitude]; Leaflet wants [latitude, longitude]: everything inside the editor is [lat, lng] and converts at the edges.
// No rule of the backend lives here (point limits, closed rings, areas: the server judges the polygon and says why not,
// docs/ZONES.md): this only moves points around and writes them back as GeoJSON.

export type LatLng = [number, number];

// One piece of a zone: the outer ring the person draws and edits (open: no repeated first point) and the holes it came with,
// which the editor keeps as they are so saving a zone drawn elsewhere does not lose them.
export interface Shape {
  outer: LatLng[];
  holes: LatLng[][];
}

// 6 decimals, about 11 cm: what the backend keeps
export const round6 = (value: number): number => Math.round(value * 1e6) / 1e6;

const isNumber = (value: unknown): value is number => typeof value === 'number' && Number.isFinite(value);

function ringFromCoordinates(ring: unknown): LatLng[] | null {
  if (!Array.isArray(ring)) return null;
  const points: LatLng[] = [];
  for (const position of ring) {
    if (!Array.isArray(position) || !isNumber(position[0]) || !isNumber(position[1])) return null;
    points.push([position[1], position[0]]);
  }
  const first = points[0];
  const last = points[points.length - 1];
  if (points.length > 1 && first && last && first[0] === last[0] && first[1] === last[1]) points.pop();
  return points;
}

function shapeFromPolygon(polygon: unknown): Shape | null {
  if (!Array.isArray(polygon) || polygon.length === 0) return null;
  const [outer, ...holes] = polygon.map(ringFromCoordinates);
  if (!outer) return null;
  return { outer, holes: holes.filter((hole): hole is LatLng[] => hole !== null) };
}

// The shapes of a GeoJSON Polygon or MultiPolygon, of a Feature or a one-feature FeatureCollection holding one, or of the JSON
// text of any of these; null when it is none of them.
export function parseGeometry(input: unknown): Shape[] | null {
  let value = input;
  if (typeof value === 'string') {
    try {
      value = JSON.parse(value);
    } catch {
      return null;
    }
  }
  if (typeof value !== 'object' || value === null) return null;
  const node = value as { type?: unknown; coordinates?: unknown; geometry?: unknown; features?: unknown };
  if (node.type === 'Feature') return parseGeometry(node.geometry);
  if (node.type === 'FeatureCollection') return Array.isArray(node.features) && node.features.length === 1 ? parseGeometry(node.features[0]) : null;
  if (node.type === 'Polygon') {
    const shape = shapeFromPolygon(node.coordinates);
    return shape ? [shape] : null;
  }
  if (node.type === 'MultiPolygon' && Array.isArray(node.coordinates)) {
    const shapes = node.coordinates.map(shapeFromPolygon);
    return shapes.length > 0 && shapes.every((shape): shape is Shape => shape !== null) ? shapes : null;
  }
  return null;
}

const toPosition = ([lat, lng]: LatLng): number[] => [round6(lng), round6(lat)];

// A closed ring ([lng, lat], first point repeated last)
function toRing(points: LatLng[]): number[][] {
  const positions = points.map(toPosition);
  return [...positions, positions[0]!];
}

// The GeoJSON of the shapes with at least three points: a Polygon for one, a MultiPolygon for several; null when none.
export function shapesToGeometry(shapes: Shape[]): ZonePolygon | null {
  const complete = shapes.filter((shape) => shape.outer.length >= 3);
  if (complete.length === 0) return null;
  const polygons = complete.map((shape) => [toRing(shape.outer), ...shape.holes.filter((hole) => hole.length >= 3).map(toRing)]);
  return polygons.length === 1 ? { type: 'Polygon', coordinates: polygons[0]! } : { type: 'MultiPolygon', coordinates: polygons };
}

export const countPoints = (shapes: Shape[]): number => shapes.reduce((sum, shape) => sum + shape.outer.length, 0);

// [[south, west], [north, east]] around the shapes (Leaflet's fitBounds); null for no points
export function boundsOf(shapes: Shape[]): [LatLng, LatLng] | null {
  const points = shapes.flatMap((shape) => shape.outer);
  if (points.length === 0) return null;
  const lats = points.map((point) => point[0]);
  const lngs = points.map((point) => point[1]);
  return [[Math.min(...lats), Math.min(...lngs)], [Math.max(...lats), Math.max(...lngs)]];
}

// Where in a ring a point on its edge goes: the index after which it is inserted. The edge closest to the point (plain
// distance on the flat lat/lng plane: a zone is a few kilometres wide).
export function nearestEdge(ring: LatLng[], point: LatLng): number {
  let best = 0;
  let bestDistance = Infinity;
  for (let index = 0; index < ring.length; index++) {
    const a = ring[index]!;
    const b = ring[(index + 1) % ring.length]!;
    const distance = distanceToSegment(point, a, b);
    if (distance < bestDistance) {
      bestDistance = distance;
      best = index;
    }
  }
  return best;
}

function distanceToSegment(p: LatLng, a: LatLng, b: LatLng): number {
  const dx = b[0] - a[0];
  const dy = b[1] - a[1];
  const lengthSquared = dx * dx + dy * dy;
  const t = lengthSquared === 0 ? 0 : Math.max(0, Math.min(1, ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / lengthSquared));
  return Math.hypot(p[0] - (a[0] + t * dx), p[1] - (a[1] + t * dy));
}

export function insertVertex(ring: LatLng[], point: LatLng): LatLng[] {
  if (ring.length < 2) return [...ring, point];
  const after = nearestEdge(ring, point);
  return [...ring.slice(0, after + 1), point, ...ring.slice(after + 1)];
}

// --- The editor's state ------------------------------------------------------------------------------------------------

// `drawing` is the index of the shape whose points are being added by clicking the map; null when the person is editing.
export interface EditorState {
  shapes: Shape[];
  drawing: number | null;
}

export type EditorAction =
  | { type: 'load'; shapes: Shape[]; drawing?: boolean }
  | { type: 'startShape' }
  | { type: 'addPoint'; point: LatLng }
  | { type: 'finish' }
  | { type: 'undo' }
  | { type: 'movePoint'; shape: number; index: number; point: LatLng }
  | { type: 'removePoint'; shape: number; index: number }
  | { type: 'insertPoint'; shape: number; point: LatLng }
  | { type: 'removeShape'; shape: number }
  | { type: 'clear' };

export function initialEditor(shapes: Shape[] = [], drawing = false): EditorState {
  if (drawing) return { shapes: [...shapes, { outer: [], holes: [] }], drawing: shapes.length };
  return { shapes, drawing: null };
}

const roundPoint = ([lat, lng]: LatLng): LatLng => [round6(lat), round6(lng)];

function replaceShape(shapes: Shape[], index: number, change: (shape: Shape) => Shape): Shape[] {
  return shapes.map((shape, position) => (position === index ? change(shape) : shape));
}

// A shape with fewer than three points is not a zone: finishing it (or starting another over it) drops it.
function dropUnfinished(state: EditorState): Shape[] {
  return state.drawing === null ? state.shapes : state.shapes.filter((shape, index) => index !== state.drawing || shape.outer.length >= 3);
}

export function editorReducer(state: EditorState, action: EditorAction): EditorState {
  switch (action.type) {
    case 'load':
      return initialEditor(action.shapes, action.drawing);
    case 'startShape': {
      const shapes = dropUnfinished(state);
      return { shapes: [...shapes, { outer: [], holes: [] }], drawing: shapes.length };
    }
    case 'addPoint': {
      if (state.drawing === null) return state;
      const point = roundPoint(action.point);
      return { ...state, shapes: replaceShape(state.shapes, state.drawing, (shape) => ({ ...shape, outer: [...shape.outer, point] })) };
    }
    case 'finish':
      return state.drawing === null ? state : { shapes: dropUnfinished(state), drawing: null };
    case 'undo': {
      if (state.drawing === null) return state;
      return { ...state, shapes: replaceShape(state.shapes, state.drawing, (shape) => ({ ...shape, outer: shape.outer.slice(0, -1) })) };
    }
    case 'movePoint': {
      const point = roundPoint(action.point);
      return { ...state, shapes: replaceShape(state.shapes, action.shape, (shape) => ({ ...shape, outer: shape.outer.map((p, i) => (i === action.index ? point : p)) })) };
    }
    case 'removePoint': {
      const shape = state.shapes[action.shape];
      // A finished shape keeps its three points: removing one more is removing the shape (a button for that)
      if (!shape || (state.drawing !== action.shape && shape.outer.length <= 3)) return state;
      return { ...state, shapes: replaceShape(state.shapes, action.shape, (s) => ({ ...s, outer: s.outer.filter((_, i) => i !== action.index) })) };
    }
    case 'insertPoint': {
      if (state.drawing !== null) return state;
      return { ...state, shapes: replaceShape(state.shapes, action.shape, (shape) => ({ ...shape, outer: insertVertex(shape.outer, roundPoint(action.point)) })) };
    }
    case 'removeShape': {
      const shapes = state.shapes.filter((_, index) => index !== action.shape);
      let drawing = state.drawing;
      if (drawing !== null) drawing = drawing === action.shape ? null : drawing > action.shape ? drawing - 1 : drawing;
      return { shapes, drawing };
    }
    case 'clear':
      return { shapes: [], drawing: null };
  }
}
