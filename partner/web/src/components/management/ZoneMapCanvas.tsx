'use client';

import 'leaflet/dist/leaflet.css';
import './zoneMap.css';
import { useEffect } from 'react';
import { divIcon, type LatLngBoundsExpression, type LeafletEvent, type LeafletMouseEvent, type Marker as LeafletMarker } from 'leaflet';
import { CircleMarker, MapContainer, Marker, Polygon, TileLayer, Tooltip, useMap, useMapEvents } from 'react-leaflet';
import { useMapConfig } from '@/lib/apiConfig';
import { useI18n } from '../I18nProvider';
import { boundsOf, type EditorAction, type EditorState, type LatLng, type Shape } from './polygon';

const MAX_ZOOM = 19;
// Where the map opens when there is nothing to show yet (Tashkent)
const DEFAULT_CENTER: LatLng = [41.2995, 69.2401];

export interface MapBranch {
  id: number;
  name: string;
  position: LatLng;
}

export interface MapZone {
  id: number;
  name: string;
  active: boolean;
  shapes: Shape[];
}

export interface ZoneMapProps {
  branches: MapBranch[];
  zones: MapZone[];
  // The zone being looked at or edited (drawn on top, in the brand colour)
  selectedId: number | null;
  // The shapes being edited; null: the map only shows
  editor: EditorState | null;
  dispatch: (action: EditorAction) => void;
  onSelectZone: (id: number) => void;
  height?: number;
}

const VERTEX_ICON = divIcon({ className: 'rz-vertex', iconSize: [24, 24], iconAnchor: [12, 12] });
const FIRST_VERTEX_ICON = divIcon({ className: 'rz-vertex rz-vertex--first', iconSize: [24, 24], iconAnchor: [12, 12] });

// Clicks on the empty map add a point of the shape being drawn
function DrawOnClick({ drawing, dispatch }: { drawing: boolean; dispatch: (action: EditorAction) => void }) {
  useMapEvents({
    click(event: LeafletMouseEvent) {
      if (drawing) dispatch({ type: 'addPoint', point: [event.latlng.lat, event.latlng.lng] });
    },
  });
  return null;
}

// Fits the view to `bounds` whenever `fitKey` changes (another zone picked, the zones just loaded), not on every edit
function FitView({ bounds, fitKey }: { bounds: LatLngBoundsExpression | null; fitKey: string }) {
  const map = useMap();
  useEffect(() => {
    // Not animated: a map removed mid-flight (the page changes) throws in the next frame
    if (bounds) map.fitBounds(bounds, { padding: [32, 32], maxZoom: 16, animate: false });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- refit on a new key only, not when an edit moves the bounds
  }, [map, fitKey]);
  return null;
}

// Leaflet measures its container once: tell it when the layout changes
function KeepSized() {
  const map = useMap();
  useEffect(() => {
    if (typeof ResizeObserver === 'undefined') return;
    const observer = new ResizeObserver(() => map.invalidateSize());
    observer.observe(map.getContainer());
    return () => observer.disconnect();
  }, [map]);
  return null;
}

function className(zone: { active: boolean }, selected: boolean): string {
  return selected ? 'rz-zone rz-zone--selected' : zone.active ? 'rz-zone rz-zone--active' : 'rz-zone';
}

const positionsOf = (shape: Shape): LatLng[][] => [shape.outer, ...shape.holes];

// The branches (red dots) and the zones of the store on OpenStreetMap, and a small vertex editor for the zone being drawn:
// click the map to add points, close the shape by clicking its first point (or the toolbar), drag a point to move it,
// click an edge to add a point there, right-click (long press on a phone) a point to remove it. No drawing library: Leaflet.draw
// is unmaintained, reaches for a global `L` and needs its own sprites and styles; this is 100 lines on react-leaflet.
export default function ZoneMapCanvas({ branches, zones, selectedId, editor, dispatch, onSelectZone, height = 520 }: ZoneMapProps) {
  const { t } = useI18n();
  const tiles = useMapConfig();
  const editing = editor !== null;
  const drawing = editor?.drawing ?? null;

  const selectedZone = zones.find((zone) => zone.id === selectedId) ?? null;
  // What the view fits to when the key below changes: the shapes being edited, else the selected zone, else every zone, else
  // the branches
  const shapes = editor && editor.shapes.length > 0 ? editor.shapes : selectedZone ? selectedZone.shapes : zones.flatMap((zone) => zone.shapes);
  const branchPoints = branches.map((branch) => branch.position);
  const bounds: LatLngBoundsExpression | null =
    boundsOf(shapes) ??
    (branchPoints.length === 0
      ? null
      : [
          [Math.min(...branchPoints.map((p) => p[0])), Math.min(...branchPoints.map((p) => p[1]))],
          [Math.max(...branchPoints.map((p) => p[0])), Math.max(...branchPoints.map((p) => p[1]))],
        ]);
  const fitKey = `${selectedId ?? 'all'}:${zones.length}:${branches.length}:${editing}`;

  const others = zones.filter((zone) => !(editing && zone.id === selectedId));

  return (
    <div className="rz-zone-map relative isolate overflow-hidden rounded-card border border-line bg-surface-2" style={{ height }} data-testid="zone-map">
      <MapContainer center={DEFAULT_CENTER} zoom={12} maxZoom={MAX_ZOOM} scrollWheelZoom className="h-full w-full">
        <TileLayer url={tiles.tileUrl} attribution={tiles.attribution} maxZoom={MAX_ZOOM} />
        <FitView bounds={bounds} fitKey={fitKey} />
        <KeepSized />
        <DrawOnClick drawing={drawing !== null} dispatch={dispatch} />

        {others.map((zone) =>
          zone.shapes.map((shape, index) => (
            <Polygon
              key={`${zone.id}-${index}-${editing}-${zone.id === selectedId}`}
              positions={positionsOf(shape)}
              className={className(zone, zone.id === selectedId)}
              bubblingMouseEvents={false}
              interactive={!editing}
              eventHandlers={{ click: () => onSelectZone(zone.id) }}
            >
              <Tooltip sticky>{zone.name}</Tooltip>
            </Polygon>
          )),
        )}

        {branches.map((branch) => (
          <CircleMarker key={`${branch.id}-${editing}`} center={branch.position} radius={8} className="rz-branch" interactive={!editing}>
            <Tooltip>{branch.name}</Tooltip>
          </CircleMarker>
        ))}

        {editor?.shapes.map((shape, shapeIndex) => (
          <Polygon
            key={`edit-${shapeIndex}`}
            positions={positionsOf(shape)}
            className="rz-zone rz-zone--selected"
            bubblingMouseEvents={false}
            eventHandlers={{
              click(event: LeafletMouseEvent) {
                const point: LatLng = [event.latlng.lat, event.latlng.lng];
                dispatch(drawing !== null ? { type: 'addPoint', point } : { type: 'insertPoint', shape: shapeIndex, point });
              },
            }}
          />
        ))}
        {editor?.shapes.map((shape, shapeIndex) =>
          shape.outer.map((point, index) => (
            <Marker
              key={`vertex-${shapeIndex}-${index}-${point[0]}-${point[1]}`}
              position={point}
              icon={index === 0 ? FIRST_VERTEX_ICON : VERTEX_ICON}
              draggable
              title={t('zones_vertexTitle', { n: index + 1 })}
              eventHandlers={{
                dragend(event: LeafletEvent) {
                  const { lat, lng } = (event.target as LeafletMarker).getLatLng();
                  dispatch({ type: 'movePoint', shape: shapeIndex, index, point: [lat, lng] });
                },
                click() {
                  // The first point of the shape being drawn closes it
                  if (drawing === shapeIndex && index === 0 && shape.outer.length >= 3) dispatch({ type: 'finish' });
                },
                contextmenu() {
                  dispatch({ type: 'removePoint', shape: shapeIndex, index });
                },
              }}
            />
          )),
        )}
      </MapContainer>
    </div>
  );
}
