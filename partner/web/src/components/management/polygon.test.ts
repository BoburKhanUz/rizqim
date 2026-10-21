import { describe, expect, it } from 'vitest';
import {
  boundsOf,
  countPoints,
  editorReducer,
  initialEditor,
  insertVertex,
  nearestEdge,
  parseGeometry,
  shapesToGeometry,
  type EditorState,
  type LatLng,
  type Shape,
} from './polygon';

const SQUARE: LatLng[] = [[41.3, 69.2], [41.3, 69.3], [41.4, 69.3], [41.4, 69.2]];
const POLYGON = { type: 'Polygon', coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.4], [69.2, 41.3]]] };

describe('GeoJSON in', () => {
  it('reads a Polygon into [lat, lng] points without the closing point', () => {
    expect(parseGeometry(POLYGON)).toEqual([{ outer: SQUARE, holes: [] }]);
  });

  it('keeps the holes of a polygon', () => {
    const hole = [[69.22, 41.32], [69.24, 41.32], [69.24, 41.34], [69.22, 41.32]];
    const shapes = parseGeometry({ type: 'Polygon', coordinates: [...POLYGON.coordinates, hole] })!;
    expect(shapes[0]!.holes).toEqual([[[41.32, 69.22], [41.32, 69.24], [41.34, 69.24]]]);
  });

  it('reads a MultiPolygon, a Feature, a one-feature collection and the JSON text of any of them', () => {
    expect(parseGeometry({ type: 'MultiPolygon', coordinates: [POLYGON.coordinates, POLYGON.coordinates] })).toHaveLength(2);
    expect(parseGeometry({ type: 'Feature', properties: {}, geometry: POLYGON })).toHaveLength(1);
    expect(parseGeometry({ type: 'FeatureCollection', features: [{ type: 'Feature', geometry: POLYGON }] })).toHaveLength(1);
    expect(parseGeometry(JSON.stringify(POLYGON))).toHaveLength(1);
  });

  it.each([
    ['broken JSON', '{"type":'],
    ['a point', { type: 'Point', coordinates: [69.2, 41.3] }],
    ['two features', { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: POLYGON }, { type: 'Feature', geometry: POLYGON }] }],
    ['text in a position', { type: 'Polygon', coordinates: [[['a', 'b']]] }],
    ['null', null],
    ['an empty polygon', { type: 'Polygon', coordinates: [] }],
  ])('refuses %s', (_name, input) => {
    expect(parseGeometry(input)).toBeNull();
  });
});

describe('GeoJSON out', () => {
  it('closes the ring and writes [lng, lat] rounded to 6 decimals', () => {
    const geometry = shapesToGeometry([{ outer: [[41.30000049, 69.2], [41.3, 69.3], [41.4, 69.3]], holes: [] }]);
    expect(geometry).toEqual({ type: 'Polygon', coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.3]]] });
  });

  it('is a MultiPolygon for several shapes and ignores a shape with fewer than three points', () => {
    const shape: Shape = { outer: SQUARE, holes: [] };
    expect(shapesToGeometry([shape, shape])?.type).toBe('MultiPolygon');
    expect(shapesToGeometry([shape, { outer: [[41, 69], [41.1, 69.1]], holes: [] }])?.type).toBe('Polygon');
    expect(shapesToGeometry([{ outer: [[41, 69], [41.1, 69.1]], holes: [] }])).toBeNull();
    expect(shapesToGeometry([])).toBeNull();
  });

  it('round-trips what the server sent, holes included', () => {
    const hole = [[69.22, 41.32], [69.24, 41.32], [69.24, 41.34], [69.22, 41.32]];
    const source = { type: 'Polygon', coordinates: [...POLYGON.coordinates, hole] };
    expect(shapesToGeometry(parseGeometry(source)!)).toEqual(source);
  });

  it('counts points and finds bounds', () => {
    const shapes = parseGeometry(POLYGON)!;
    expect(countPoints(shapes)).toBe(4);
    expect(boundsOf(shapes)).toEqual([[41.3, 69.2], [41.4, 69.3]]);
    expect(boundsOf([])).toBeNull();
  });
});

describe('vertices', () => {
  it('puts a new point on the nearest edge', () => {
    expect(nearestEdge(SQUARE, [41.35, 69.31])).toBe(1); // right edge: between points 1 and 2
    expect(insertVertex(SQUARE, [41.35, 69.31])).toEqual([[41.3, 69.2], [41.3, 69.3], [41.35, 69.31], [41.4, 69.3], [41.4, 69.2]]);
    // the closing edge (last point back to the first)
    expect(insertVertex(SQUARE, [41.35, 69.19])).toEqual([...SQUARE, [41.35, 69.19]]);
  });
});

describe('the editor', () => {
  const draw = (...points: LatLng[]): EditorState => points.reduce<EditorState>((state, point) => editorReducer(state, { type: 'addPoint', point }), initialEditor([], true));

  it('starts a new zone in drawing mode and collects the clicked points', () => {
    const state = draw([41.3, 69.2], [41.3, 69.3], [41.4, 69.3]);
    expect(state.drawing).toBe(0);
    expect(state.shapes[0]!.outer).toHaveLength(3);
    expect(shapesToGeometry(state.shapes)?.type).toBe('Polygon');
  });

  it('rounds clicked points to 6 decimals', () => {
    expect(draw([41.30000049, 69.20000051]).shapes[0]!.outer[0]).toEqual([41.3, 69.200001]);
  });

  it('closes a shape with three points and ignores clicks afterwards', () => {
    let state = draw([41.3, 69.2], [41.3, 69.3], [41.4, 69.3]);
    state = editorReducer(state, { type: 'finish' });
    expect(state.drawing).toBeNull();
    expect(editorReducer(state, { type: 'addPoint', point: [1, 1] })).toBe(state);
  });

  it('drops an unfinished shape of fewer than three points when finishing or starting another', () => {
    const two = draw([41.3, 69.2], [41.3, 69.3]);
    expect(editorReducer(two, { type: 'finish' }).shapes).toEqual([]);
    expect(editorReducer(two, { type: 'startShape' })).toEqual({ shapes: [{ outer: [], holes: [] }], drawing: 0 });
  });

  it('undoes the last point while drawing', () => {
    const state = editorReducer(draw([41.3, 69.2], [41.3, 69.3]), { type: 'undo' });
    expect(state.shapes[0]!.outer).toEqual([[41.3, 69.2]]);
    const idle = initialEditor(parseGeometry(POLYGON)!);
    expect(editorReducer(idle, { type: 'undo' })).toBe(idle);
  });

  it('moves a point, inserts one on an edge and removes one, keeping three at least', () => {
    const idle = initialEditor(parseGeometry(POLYGON)!);
    const moved = editorReducer(idle, { type: 'movePoint', shape: 0, index: 2, point: [41.45, 69.35] });
    expect(moved.shapes[0]!.outer[2]).toEqual([41.45, 69.35]);
    const inserted = editorReducer(idle, { type: 'insertPoint', shape: 0, point: [41.35, 69.31] });
    expect(inserted.shapes[0]!.outer).toHaveLength(5);
    const removed = editorReducer(inserted, { type: 'removePoint', shape: 0, index: 2 });
    expect(removed.shapes[0]!.outer).toHaveLength(4);
    const triangle = editorReducer(removed, { type: 'removePoint', shape: 0, index: 0 });
    expect(triangle.shapes[0]!.outer).toHaveLength(3);
    expect(editorReducer(triangle, { type: 'removePoint', shape: 0, index: 0 })).toBe(triangle);
  });

  it('does not insert a point by clicking an edge while drawing', () => {
    const state = draw([41.3, 69.2], [41.3, 69.3], [41.4, 69.3]);
    expect(editorReducer(state, { type: 'insertPoint', shape: 0, point: [41.35, 69.25] })).toBe(state);
  });

  it('removes a shape, adjusting the one being drawn, and clears everything', () => {
    const base = initialEditor(parseGeometry({ type: 'MultiPolygon', coordinates: [POLYGON.coordinates, POLYGON.coordinates] })!);
    expect(editorReducer(base, { type: 'removeShape', shape: 0 }).shapes).toHaveLength(1);
    const drawing = editorReducer(base, { type: 'startShape' });
    expect(drawing.drawing).toBe(2);
    expect(editorReducer(drawing, { type: 'removeShape', shape: 0 }).drawing).toBe(1);
    expect(editorReducer(drawing, { type: 'removeShape', shape: 2 }).drawing).toBeNull();
    expect(editorReducer(drawing, { type: 'clear' })).toEqual({ shapes: [], drawing: null });
  });

  it('loads shapes from outside (the GeoJSON box)', () => {
    expect(editorReducer(draw([41, 69]), { type: 'load', shapes: parseGeometry(POLYGON)! }).drawing).toBeNull();
  });
});
