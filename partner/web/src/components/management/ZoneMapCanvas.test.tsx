import { act, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from '@/test/helpers';
import ZoneMapCanvas, { type ZoneMapProps } from './ZoneMapCanvas';
import { initialEditor, parseGeometry } from './polygon';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

// Leaflet needs a real browser layout; what is under test is what the component asks the map to draw and what it does with the
// map's events. react-leaflet is replaced by plain elements that record their props (the map's own behaviour is Leaflet's).
const fake = vi.hoisted(() => ({
  fitBounds: vi.fn(),
  mapEvents: {} as Record<string, (event: unknown) => void>,
  polygons: [] as Array<{ positions: unknown; className: string; interactive?: boolean; eventHandlers?: Record<string, (event: unknown) => void> }>,
  markers: [] as Array<{ position: [number, number]; title: string; eventHandlers: Record<string, (event?: unknown) => void> }>,
  circles: [] as Array<{ center: [number, number] }>,
}));

vi.mock('leaflet', () => ({ divIcon: (options: unknown) => ({ options }) }));
vi.mock('leaflet/dist/leaflet.css', () => ({}));
vi.mock('react-leaflet', () => ({
  MapContainer: ({ children }: { children: React.ReactNode }) => <div data-testid="leaflet">{children}</div>,
  TileLayer: ({ url, attribution }: { url: string; attribution: string }) => <div data-testid="tiles" data-url={url} data-attribution={attribution} />,
  useMap: () => ({ fitBounds: fake.fitBounds, invalidateSize: vi.fn(), getContainer: () => document.createElement('div') }),
  useMapEvents: (events: Record<string, (event: unknown) => void>) => {
    fake.mapEvents = events;
    return null;
  },
  Polygon: (props: (typeof fake.polygons)[number] & { children?: React.ReactNode }) => {
    fake.polygons.push(props);
    return <div data-testid="polygon">{props.children}</div>;
  },
  CircleMarker: (props: { center: [number, number]; children?: React.ReactNode }) => {
    fake.circles.push(props);
    return <div data-testid="branch">{props.children}</div>;
  },
  Marker: (props: (typeof fake.markers)[number]) => {
    fake.markers.push(props);
    return <div data-testid="vertex" title={props.title} />;
  },
  Tooltip: ({ children }: { children: React.ReactNode }) => <span>{children}</span>,
}));

const SQUARE = { type: 'Polygon', coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.4], [69.2, 41.3]]] };

beforeEach(() => {
  fake.fitBounds.mockClear();
  fake.polygons.length = 0;
  fake.markers.length = 0;
  fake.circles.length = 0;
  fake.mapEvents = {};
});

function setup(over: Partial<ZoneMapProps> = {}) {
  const dispatch = vi.fn();
  const onSelectZone = vi.fn();
  const props: ZoneMapProps = {
    branches: [{ id: 1, name: 'Markaz', position: [41.31, 69.27] }],
    zones: [{ id: 7, name: 'Markaz hududi', active: true, shapes: parseGeometry(SQUARE)! }, { id: 8, name: 'Eski', active: false, shapes: parseGeometry(SQUARE)! }],
    selectedId: null,
    editor: null,
    dispatch,
    onSelectZone,
    ...over,
  };
  renderPage(<ZoneMapCanvas {...props} />);
  return { dispatch, onSelectZone };
}

describe('ZoneMapCanvas: showing', () => {
  it('draws the OpenStreetMap tiles with their attribution, the branches and every zone', () => {
    setup();
    expect(screen.getByTestId('tiles')).toHaveAttribute('data-url', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
    expect(screen.getByTestId('tiles').getAttribute('data-attribution')).toContain('OpenStreetMap');
    expect(fake.circles.map((circle) => circle.center)).toEqual([[41.31, 69.27]]);
    expect(screen.getAllByTestId('polygon')).toHaveLength(2);
    expect(screen.getByText('Markaz')).toBeInTheDocument(); // the branch's tooltip
    expect(screen.getByText('Markaz hududi')).toBeInTheDocument();
    expect(fake.polygons.map((polygon) => polygon.className)).toEqual(['rz-zone rz-zone--active', 'rz-zone']);
    expect(screen.queryAllByTestId('vertex')).toHaveLength(0);
  });

  it('fits the view to the zones, and to the selected zone when there is one', () => {
    setup({ selectedId: 7 });
    expect(fake.fitBounds).toHaveBeenCalledWith([[41.3, 69.2], [41.4, 69.3]], expect.objectContaining({ animate: false }));
    expect(fake.polygons.find((polygon) => polygon.className.includes('selected'))).toBeDefined();
  });

  it('opens a zone when its shape is clicked', () => {
    const { onSelectZone } = setup();
    act(() => fake.polygons[1]!.eventHandlers!.click!({}));
    expect(onSelectZone).toHaveBeenCalledWith(8);
  });

  it('does not react to map clicks when nothing is being drawn', () => {
    const { dispatch } = setup();
    act(() => fake.mapEvents.click!({ latlng: { lat: 41.3, lng: 69.2 } }));
    expect(dispatch).not.toHaveBeenCalled();
  });
});

describe('ZoneMapCanvas: editing', () => {
  it('turns map clicks into points while a shape is being drawn', () => {
    const { dispatch } = setup({ editor: initialEditor([], true) });
    act(() => fake.mapEvents.click!({ latlng: { lat: 41.3, lng: 69.2 } }));
    expect(dispatch).toHaveBeenCalledWith({ type: 'addPoint', point: [41.3, 69.2] });
  });

  it('shows a draggable vertex per point of the shape being edited and makes the other zones inert', () => {
    setup({ selectedId: 7, editor: initialEditor(parseGeometry(SQUARE)!) });
    expect(screen.getAllByTestId('vertex')).toHaveLength(4);
    expect(fake.markers[0]!.title).toBe('1-nuqta');
    // The zone being edited is drawn once, by the editor; the other one cannot be clicked away to
    const others = fake.polygons.filter((polygon) => polygon.interactive === false);
    expect(others).toHaveLength(1);
  });

  it('moves a point when its vertex is dragged', () => {
    const { dispatch } = setup({ selectedId: 7, editor: initialEditor(parseGeometry(SQUARE)!) });
    act(() => fake.markers[2]!.eventHandlers.dragend!({ target: { getLatLng: () => ({ lat: 41.45, lng: 69.35 }) } }));
    expect(dispatch).toHaveBeenCalledWith({ type: 'movePoint', shape: 0, index: 2, point: [41.45, 69.35] });
  });

  it('removes a point on a right-click of its vertex', () => {
    const { dispatch } = setup({ selectedId: 7, editor: initialEditor(parseGeometry(SQUARE)!) });
    act(() => fake.markers[1]!.eventHandlers.contextmenu!());
    expect(dispatch).toHaveBeenCalledWith({ type: 'removePoint', shape: 0, index: 1 });
  });

  it('adds a point on an edge when the shape is clicked, and a click on the shape draws while drawing', () => {
    const idle = setup({ selectedId: 7, editor: initialEditor(parseGeometry(SQUARE)!) });
    const editable = fake.polygons.filter((polygon) => polygon.interactive !== false).at(-1)!;
    act(() => editable.eventHandlers!.click!({ latlng: { lat: 41.35, lng: 69.31 } }));
    expect(idle.dispatch).toHaveBeenCalledWith({ type: 'insertPoint', shape: 0, point: [41.35, 69.31] });

    fake.polygons.length = 0;
    fake.markers.length = 0;
    document.body.innerHTML = '';
    const drawing = setup({ editor: initialEditor(parseGeometry(SQUARE)!, true) });
    const outline = fake.polygons.filter((polygon) => polygon.interactive !== false)[0]!;
    act(() => outline.eventHandlers!.click!({ latlng: { lat: 41.36, lng: 69.22 } }));
    expect(drawing.dispatch).toHaveBeenCalledWith({ type: 'addPoint', point: [41.36, 69.22] });
  });

  it('closes the shape being drawn by clicking its first point, once it has three', () => {
    let state = initialEditor([], true);
    state = { ...state, shapes: [{ outer: [[41.3, 69.2], [41.3, 69.3], [41.4, 69.3]], holes: [] }] };
    const { dispatch } = setup({ editor: state });
    act(() => fake.markers[1]!.eventHandlers.click!());
    expect(dispatch).not.toHaveBeenCalled();
    act(() => fake.markers[0]!.eventHandlers.click!());
    expect(dispatch).toHaveBeenCalledWith({ type: 'finish' });
  });

  it('does not close a shape of two points', () => {
    const state = { shapes: [{ outer: [[41.3, 69.2], [41.3, 69.3]] as [number, number][], holes: [] }], drawing: 0 };
    const { dispatch } = setup({ editor: state });
    act(() => fake.markers[0]!.eventHandlers.click!());
    expect(dispatch).not.toHaveBeenCalled();
  });
});
