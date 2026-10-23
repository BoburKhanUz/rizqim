'use client';

import { useMemo, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react';
import { Armchair, MoveDiagonal2, Save, TriangleAlert, Undo2 } from 'lucide-react';
import { Button, Card, EmptyState, Field, cx, fieldClass } from '@rizqim/ui';
import { useHalls, useSaveFloorLayout, type FloorRect, type Hall } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { FormError, Notice } from '../management/Notice';
import { Gate } from '../pos/Gate';
import { labelFor } from '../reports/format';
import { clampRect, firstFreeSpot, GRID, layoutOf, moveRect, overlappingIds, pixelsToUnits, resizeRect, sameRect, sizeOf, toLayoutInput } from './layoutModel';

interface Place {
  id: number;
  hallId: number;
  name: string;
  kind: string;
  seats: number | null;
  active: boolean;
  // Where it is now in the editor, and where the server has it (null: never placed)
  rect: FloorRect;
  saved: FloorRect | null;
}

// The places of every hall of the branch as the editor holds them. A place that was never positioned gets the first free
// spot of its hall (and counts as changed, so "Saqlash" keeps it).
function placesOf(halls: Hall[]): Place[] {
  const places: Place[] = [];
  for (const hall of halls) {
    const placed: FloorRect[] = hall.tables.map((table) => layoutOf(table)).filter((rect): rect is FloorRect => rect !== null).map((rect) => clampRect(rect));
    for (const table of hall.tables) {
      const saved = layoutOf(table);
      const rect = saved ? clampRect(saved) : firstFreeSpot(sizeOf(table.kind), placed);
      if (!saved) placed.push(rect);
      places.push({ id: table.id, hallId: hall.id, name: table.name, kind: table.kind, seats: table.seats, active: table.is_active, rect, saved });
    }
  }
  return places;
}

// A fingerprint of what the server holds, so the editor starts over when the layout changed behind its back (another
// device, a refetch after a save) and not when only something unrelated did
const fingerprint = (halls: Hall[]) => JSON.stringify(halls.map((hall) => [hall.id, hall.name, hall.tables.map((table) => [table.id, table.name, table.kind, table.is_active, table.layout])]));

// The floor map: a grid per hall where places are dragged to position and resized by their corner, with the keyboard (arrows
// move a cell, Shift + arrows resize) or with the numbers below. One "Saqlash" saves the whole branch (PUT /owner/dining-tables/layout).
export function LayoutEditor({ branchId, canEdit }: { branchId: number; canEdit: boolean }) {
  const halls = useHalls(branchId);
  // What survives the editor starting over (after a save the halls are read again and the editor is rebuilt from them):
  // the hall on show and the "saved" note
  const [hallId, setHallId] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  return (
    <Gate query={halls} count={2}>
      {(data) => <Editor key={`${branchId}:${fingerprint(data)}`} branchId={branchId} halls={data} canEdit={canEdit} hallId={hallId} onHall={setHallId} notice={notice} onNotice={setNotice} />}
    </Gate>
  );
}

function Editor({ branchId, halls, canEdit, hallId, onHall, notice, onNotice }: { branchId: number; halls: Hall[]; canEdit: boolean; hallId: number | null; onHall: (id: number) => void; notice: string | null; onNotice: (message: string | null) => void }) {
  const { t } = useI18n();
  const [places, setPlaces] = useState<Place[]>(() => placesOf(halls));
  const [selected, setSelected] = useState<number | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const setNotice = onNotice;
  const save = useSaveFloorLayout();

  const hall = halls.find((candidate) => candidate.id === hallId) ?? halls[0];
  const shown = places.filter((place) => place.hallId === hall?.id);
  const crowded = useMemo(() => overlappingIds(shown.map((place) => ({ id: place.id, rect: place.rect }))), [shown]);
  const changed = places.filter((place) => !sameRect(place.rect, place.saved));
  const current = places.find((place) => place.id === selected) ?? null;

  const setRect = (id: number, rect: FloorRect) => {
    setNotice(null);
    setPlaces((all) => all.map((place) => (place.id === id ? { ...place, rect } : place)));
  };

  const submit = () => {
    setFailure(null);
    setNotice(null);
    const rows = changed.map((place) => ({ id: place.id, hall_id: place.hallId, layout: toLayoutInput(place.rect) }));
    save.mutate(
      { branchId, tables: rows },
      {
        onSuccess: () => {
          setPlaces((all) => all.map((place) => ({ ...place, saved: place.rect })));
          setNotice(t('floor_map_saved'));
        },
        onError: (error) => setFailure(errorText(error, t)),
      },
    );
  };

  if (!hall) return <EmptyState icon={Armchair} title={t('floor_halls_empty')} text={t('floor_map_noHalls')} />;

  return (
    <div className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">{canEdit ? t('floor_map_intro') : t('floor_readOnly')}</p>
      <Notice message={notice} />
      <FormError message={failure} />

      <div className="flex flex-wrap items-end justify-between gap-3">
        <Field label={t('floor_map_hall')}>
          {(props) => (
            <select {...props} className={`${fieldClass} sm:min-w-64`} value={hall.id} onChange={(event) => {
              onHall(Number(event.target.value));
              setSelected(null);
            }}>
              {halls.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          )}
        </Field>
        {canEdit && (
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-footnote text-fg-muted" aria-live="polite">
              {changed.length > 0 ? t('floor_map_changed', { n: changed.length }) : t('floor_map_nothingChanged')}
            </p>
            <Button variant="secondary" icon={Undo2} onClick={() => setPlaces(placesOf(halls))} disabled={changed.length === 0 || save.isPending}>
              {t('floor_map_reset')}
            </Button>
            <Button icon={Save} onClick={submit} disabled={changed.length === 0} loading={save.isPending}>
              {t('floor_map_save')}
            </Button>
          </div>
        )}
      </div>

      {shown.length === 0 ? (
        <EmptyState icon={Armchair} title={t('floor_map_empty')} text={t('floor_map_emptyText')} />
      ) : (
        <Canvas places={shown} crowded={crowded} selected={selected} canEdit={canEdit} onSelect={setSelected} onChange={setRect} />
      )}

      {crowded.size > 0 && (
        <p role="note" className="flex items-start gap-2 rounded-card bg-warning-soft px-4 py-3 text-footnote text-warning-soft-fg">
          <TriangleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
          {t('floor_map_overlap')}
        </p>
      )}

      {current && (
        <Card className="flex flex-col gap-3" aria-label={t('floor_map_selected', { name: current.name })}>
          <h2 className="text-headline font-bold text-fg">{current.name}</h2>
          <p className="text-footnote text-fg-muted">
            {labelFor(t, 'floor_kind_', current.kind)}
            {current.seats ? ` · ${t('floor_map_seats', { n: current.seats })}` : ''}
            {current.saved === null ? ` · ${t('floor_map_unsaved')}` : ''}
          </p>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {(['x', 'y', 'w', 'h'] as const).map((key) => (
              <Field key={key} label={t(`floor_map_${key}`)}>
                {(props) => (
                  <input
                    {...props}
                    type="number"
                    inputMode="numeric"
                    min={key === 'w' || key === 'h' ? 1 : 0}
                    disabled={!canEdit}
                    className={fieldClass}
                    value={current.rect[key]}
                    onChange={(event) => {
                      const value = Number(event.target.value);
                      if (event.target.value !== '' && Number.isFinite(value)) setRect(current.id, clampRect({ ...current.rect, [key]: value }));
                    }}
                  />
                )}
              </Field>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}

interface DragState {
  id: number;
  mode: 'move' | 'resize';
  startX: number;
  startY: number;
  origin: FloorRect;
}

const TONE: Record<string, string> = {
  room: 'border-brand bg-brand-soft text-brand-soft-fg',
  tapchan: 'border-info bg-info-soft text-info-soft-fg',
  table: 'border-line bg-surface-2 text-fg',
};

function Canvas({ places, crowded, selected, canEdit, onSelect, onChange }: { places: Place[]; crowded: Set<number>; selected: number | null; canEdit: boolean; onSelect: (id: number | null) => void; onChange: (id: number, rect: FloorRect) => void }) {
  const { t } = useI18n();
  const canvas = useRef<HTMLDivElement>(null);
  const drag = useRef<DragState | null>(null);

  const begin = (event: PointerEvent<HTMLElement>, place: Place, mode: DragState['mode']) => {
    // The canvas deselects on a press of its own: a press on a place is not that
    event.stopPropagation();
    onSelect(place.id);
    if (!canEdit || event.button !== 0) return;
    event.currentTarget.setPointerCapture?.(event.pointerId);
    drag.current = { id: place.id, mode, startX: event.clientX, startY: event.clientY, origin: place.rect };
  };

  const track = (event: PointerEvent<HTMLElement>) => {
    const state = drag.current;
    const box = canvas.current?.getBoundingClientRect();
    if (!state || !box) return;
    const dx = pixelsToUnits(event.clientX - state.startX, box.width, GRID.cols);
    const dy = pixelsToUnits(event.clientY - state.startY, box.height, GRID.rows);
    onChange(state.id, state.mode === 'move' ? moveRect(state.origin, dx, dy) : resizeRect(state.origin, dx, dy));
  };

  const end = () => {
    drag.current = null;
  };

  const keys = (event: KeyboardEvent<HTMLElement>, place: Place) => {
    const step: Record<string, [number, number]> = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
    const delta = step[event.key];
    if (event.key === 'Escape') onSelect(null);
    if (!delta || !canEdit) return;
    event.preventDefault();
    onChange(place.id, event.shiftKey ? resizeRect(place.rect, delta[0], delta[1]) : moveRect(place.rect, delta[0], delta[1]));
  };

  return (
    <div
      ref={canvas}
      data-testid="floor-canvas"
      role="group"
      aria-label={t('floor_map_canvas')}
      onPointerDown={() => onSelect(null)}
      className="relative w-full overflow-hidden rounded-card border border-line bg-surface"
      style={{
        aspectRatio: `${GRID.cols} / ${GRID.rows}`,
        backgroundImage: 'linear-gradient(to right, var(--rz-line) 1px, transparent 1px), linear-gradient(to bottom, var(--rz-line) 1px, transparent 1px)',
        backgroundSize: `${100 / GRID.cols}% ${100 / GRID.rows}%`,
      }}
    >
      {places.map((place) => {
        const { x, y, w, h } = place.rect;
        return (
          <div
            key={place.id}
            role="button"
            tabIndex={0}
            data-testid={`place-${place.id}`}
            data-rect={`${x},${y},${w},${h}`}
            aria-pressed={selected === place.id}
            aria-label={t('floor_map_placeAria', { name: place.name, x, y, w, h })}
            onPointerDown={(event) => begin(event, place, 'move')}
            onPointerMove={track}
            onPointerUp={end}
            onPointerCancel={end}
            onKeyDown={(event) => keys(event, place)}
            onFocus={() => onSelect(place.id)}
            className={cx(
              'absolute flex touch-none select-none flex-col items-center justify-center overflow-hidden rounded-control border-2 p-1 text-center text-caption font-bold',
              'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
              canEdit ? 'cursor-grab active:cursor-grabbing' : 'cursor-default',
              TONE[place.kind] ?? TONE.table,
              !place.active && 'opacity-50',
              crowded.has(place.id) && 'border-danger',
              selected === place.id && 'ring-2 ring-brand',
            )}
            style={{ left: `${(x / GRID.cols) * 100}%`, top: `${(y / GRID.rows) * 100}%`, width: `${(w / GRID.cols) * 100}%`, height: `${(h / GRID.rows) * 100}%` }}
          >
            <span className="max-w-full truncate">{place.name}</span>
            <span className="max-w-full truncate font-medium opacity-80">{labelFor(t, 'floor_kind_', place.kind)}</span>
            {canEdit && (
              <span
                role="presentation"
                data-testid={`resize-${place.id}`}
                onPointerDown={(event) => begin(event, place, 'resize')}
                onPointerMove={track}
                onPointerUp={end}
                onPointerCancel={end}
                className="absolute bottom-0 right-0 flex size-5 cursor-nwse-resize touch-none items-center justify-center rounded-tl-control bg-fg/10"
              >
                <MoveDiagonal2 aria-hidden="true" className="size-3.5" />
              </span>
            )}
          </div>
        );
      })}
    </div>
  );
}
