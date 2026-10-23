import type { DiningTable, FloorRect, TableKind } from '@rizqim/staff';

// The arithmetic of the floor-map editor. The map is a grid of cells; a place is a rectangle of whole cells. The API keeps
// `x, y, w, h` in "map units" without saying which (the POS scales the map to its screen, so only the proportions matter): the
// panel uses grid cells, one unit per cell.

export interface Grid {
  cols: number;
  rows: number;
}

export const GRID: Grid = { cols: 40, rows: 24 };

// What a new place starts as, per kind: a table is small, a tapchan is a platform, a room is a cabin
export const DEFAULT_SIZE: Record<TableKind, { w: number; h: number }> = {
  table: { w: 2, h: 2 },
  tapchan: { w: 4, h: 3 },
  room: { w: 5, h: 4 },
};

export const MIN_SIZE = 1;

export const sizeOf = (kind: string): { w: number; h: number } => DEFAULT_SIZE[kind as TableKind] ?? DEFAULT_SIZE.table;

const finite = (value: number, fallback: number) => (Number.isFinite(value) ? value : fallback);

// A rectangle kept inside the grid: at least one cell big, no bigger than the grid, whole cells
export function clampRect(rect: FloorRect, grid: Grid = GRID): FloorRect {
  const w = Math.min(grid.cols, Math.max(MIN_SIZE, Math.round(finite(rect.w, MIN_SIZE))));
  const h = Math.min(grid.rows, Math.max(MIN_SIZE, Math.round(finite(rect.h, MIN_SIZE))));
  const x = Math.min(grid.cols - w, Math.max(0, Math.round(finite(rect.x, 0))));
  const y = Math.min(grid.rows - h, Math.max(0, Math.round(finite(rect.y, 0))));
  return { x, y, w, h };
}

// Dragged by `dx`, `dy` cells (fractions are rounded to the nearest cell)
export const moveRect = (rect: FloorRect, dx: number, dy: number, grid: Grid = GRID): FloorRect => clampRect({ ...rect, x: rect.x + Math.round(dx), y: rect.y + Math.round(dy) }, grid);

// Resized from the bottom-right corner by `dw`, `dh` cells; the top-left stays
export function resizeRect(rect: FloorRect, dw: number, dh: number, grid: Grid = GRID): FloorRect {
  const start = clampRect(rect, grid);
  // The corner stops at the wall; the place does not slide to make room
  const w = Math.min(grid.cols - start.x, Math.max(MIN_SIZE, start.w + Math.round(dw)));
  const h = Math.min(grid.rows - start.y, Math.max(MIN_SIZE, start.h + Math.round(dh)));
  return { x: start.x, y: start.y, w, h };
}

// A pointer travelled `px` pixels over a canvas `canvasPx` wide that holds `units` cells
export const pixelsToUnits = (px: number, canvasPx: number, units: number): number => (canvasPx > 0 ? (px / canvasPx) * units : 0);

export const sameRect = (a: FloorRect | null | undefined, b: FloorRect | null | undefined): boolean => (a && b ? a.x === b.x && a.y === b.y && a.w === b.w && a.h === b.h : a === b);

const num = (value: string | number | null | undefined): number | null => {
  if (value === null || value === undefined || value === '') return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
};

// The saved layout of a place as a rectangle; null while the place has none (never placed, or a zero size)
export function layoutOf(table: Pick<DiningTable, 'layout'>): FloorRect | null {
  const { x, y, w, h } = table.layout ?? { x: null, y: null, w: null, h: null };
  const [nx, ny, nw, nh] = [num(x), num(y), num(w), num(h)];
  if (nx === null || ny === null || nw === null || nh === null || nw <= 0 || nh <= 0) return null;
  return { x: nx, y: ny, w: nw, h: nh };
}

export const overlaps = (a: FloorRect, b: FloorRect): boolean => a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;

// The ids of places that sit on top of another one
export function overlappingIds(items: ReadonlyArray<{ id: number; rect: FloorRect }>): Set<number> {
  const found = new Set<number>();
  for (let i = 0; i < items.length; i++) {
    for (let j = i + 1; j < items.length; j++) {
      if (overlaps(items[i]!.rect, items[j]!.rect)) {
        found.add(items[i]!.id);
        found.add(items[j]!.id);
      }
    }
  }
  return found;
}

// The first free spot for a `size` rectangle, looking row by row from the top-left; the top-left corner when the grid is full
export function firstFreeSpot(size: { w: number; h: number }, taken: readonly FloorRect[], grid: Grid = GRID): FloorRect {
  const w = Math.min(size.w, grid.cols);
  const h = Math.min(size.h, grid.rows);
  for (let y = 0; y + h <= grid.rows; y++) {
    for (let x = 0; x + w <= grid.cols; x++) {
      const candidate = { x, y, w, h };
      if (!taken.some((other) => overlaps(candidate, other))) return candidate;
    }
  }
  return { x: 0, y: 0, w, h };
}

// What the API takes for a place's layout
export const toLayoutInput = (rect: FloorRect): FloorRect => ({ x: rect.x, y: rect.y, w: rect.w, h: rect.h });
