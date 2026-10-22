import { describe, expect, it } from 'vitest';
import { clampRect, DEFAULT_SIZE, firstFreeSpot, GRID, layoutOf, moveRect, overlappingIds, overlaps, pixelsToUnits, resizeRect, sameRect, sizeOf } from './layoutModel';

describe('clampRect', () => {
  it('keeps a place inside the grid and at least one cell big', () => {
    expect(clampRect({ x: -3, y: 50, w: 2, h: 2 })).toEqual({ x: 0, y: GRID.rows - 2, w: 2, h: 2 });
    expect(clampRect({ x: 39, y: 0, w: 5, h: 0 })).toEqual({ x: 35, y: 0, w: 5, h: 1 });
    expect(clampRect({ x: 0, y: 0, w: 500, h: 500 })).toEqual({ x: 0, y: 0, w: GRID.cols, h: GRID.rows });
  });

  it('rounds to whole cells and survives a number that is not one', () => {
    expect(clampRect({ x: 1.4, y: 2.6, w: 2.2, h: 3.5 })).toEqual({ x: 1, y: 3, w: 2, h: 4 });
    expect(clampRect({ x: NaN, y: NaN, w: NaN, h: NaN })).toEqual({ x: 0, y: 0, w: 1, h: 1 });
  });
});

describe('moving and resizing', () => {
  it('moves by cells and stops at the walls', () => {
    expect(moveRect({ x: 5, y: 5, w: 4, h: 3 }, 2.4, -1.6)).toEqual({ x: 7, y: 3, w: 4, h: 3 });
    expect(moveRect({ x: 5, y: 5, w: 4, h: 3 }, -20, 100)).toEqual({ x: 0, y: 21, w: 4, h: 3 });
  });

  it('resizes from the bottom-right corner and never below one cell or past the wall', () => {
    expect(resizeRect({ x: 5, y: 5, w: 4, h: 3 }, 2, 1)).toEqual({ x: 5, y: 5, w: 6, h: 4 });
    expect(resizeRect({ x: 5, y: 5, w: 4, h: 3 }, -10, -10)).toEqual({ x: 5, y: 5, w: 1, h: 1 });
    expect(resizeRect({ x: 38, y: 22, w: 2, h: 2 }, 5, 5)).toEqual({ x: 38, y: 22, w: 2, h: 2 });
    expect(resizeRect({ x: 30, y: 10, w: 2, h: 2 }, 50, 50)).toEqual({ x: 30, y: 10, w: 10, h: 14 });
  });

  it('turns a pointer travel into cells', () => {
    expect(pixelsToUnits(100, 800, 40)).toBe(5);
    expect(pixelsToUnits(100, 0, 40)).toBe(0);
  });
});

describe('what the API stores', () => {
  it('reads a saved layout and treats a missing or empty one as unplaced', () => {
    expect(layoutOf({ layout: { x: '1.00', y: '2.50', w: '4.00', h: '3.00' } })).toEqual({ x: 1, y: 2.5, w: 4, h: 3 });
    expect(layoutOf({ layout: { x: null, y: null, w: null, h: null } })).toBeNull();
    expect(layoutOf({ layout: { x: '1', y: '1', w: '0', h: '2' } })).toBeNull();
  });

  it('compares layouts', () => {
    expect(sameRect({ x: 1, y: 1, w: 2, h: 2 }, { x: 1, y: 1, w: 2, h: 2 })).toBe(true);
    expect(sameRect({ x: 1, y: 1, w: 2, h: 2 }, { x: 1, y: 2, w: 2, h: 2 })).toBe(false);
    expect(sameRect(null, null)).toBe(true);
    expect(sameRect(null, { x: 0, y: 0, w: 1, h: 1 })).toBe(false);
  });
});

describe('overlaps', () => {
  it('knows rectangles that share cells, and ones that only touch', () => {
    expect(overlaps({ x: 0, y: 0, w: 2, h: 2 }, { x: 1, y: 1, w: 2, h: 2 })).toBe(true);
    expect(overlaps({ x: 0, y: 0, w: 2, h: 2 }, { x: 2, y: 0, w: 2, h: 2 })).toBe(false);
  });

  it('names the places that sit on another', () => {
    const items = [
      { id: 1, rect: { x: 0, y: 0, w: 3, h: 3 } },
      { id: 2, rect: { x: 2, y: 2, w: 3, h: 3 } },
      { id: 3, rect: { x: 10, y: 10, w: 2, h: 2 } },
    ];
    expect([...overlappingIds(items)].sort()).toEqual([1, 2]);
  });
});

describe('firstFreeSpot', () => {
  it('puts a new place at the first gap, row by row', () => {
    expect(firstFreeSpot(DEFAULT_SIZE.table, [])).toEqual({ x: 0, y: 0, w: 2, h: 2 });
    expect(firstFreeSpot(DEFAULT_SIZE.table, [{ x: 0, y: 0, w: 2, h: 2 }])).toEqual({ x: 2, y: 0, w: 2, h: 2 });
    const fullRow = [{ x: 0, y: 0, w: GRID.cols, h: 2 }];
    expect(firstFreeSpot(DEFAULT_SIZE.table, fullRow)).toEqual({ x: 0, y: 2, w: 2, h: 2 });
  });

  it('falls back to the corner when the grid is full', () => {
    expect(firstFreeSpot({ w: 2, h: 2 }, [{ x: 0, y: 0, w: GRID.cols, h: GRID.rows }])).toEqual({ x: 0, y: 0, w: 2, h: 2 });
  });

  it('has a size for every kind and a default for an unknown one', () => {
    expect(sizeOf('room')).toEqual({ w: 5, h: 4 });
    expect(sizeOf('whatever')).toEqual(DEFAULT_SIZE.table);
  });
});
