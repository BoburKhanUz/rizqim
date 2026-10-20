import { describe, expect, it } from 'vitest';
import { isInventedPoint, isOnAnyBranch, toCoordinate, toLatLng } from './coordinates';

describe('toCoordinate', () => {
  it('accepts numbers and numeric strings (DECIMAL columns arrive as strings)', () => {
    expect(toCoordinate(41.311081)).toBe(41.311081);
    expect(toCoordinate('41.311081')).toBe(41.311081);
    expect(toCoordinate(' 69.24 ')).toBe(69.24);
    expect(toCoordinate(0)).toBe(0);
  });

  it('rejects everything else', () => {
    for (const value of [null, undefined, '', '   ', 'abc', NaN, Infinity, {}, [], true]) {
      expect(toCoordinate(value)).toBeNull();
    }
  });
});

describe('toLatLng', () => {
  it('returns the point for usable coordinates, whatever their type', () => {
    expect(toLatLng(41.3, 69.2)).toEqual({ lat: 41.3, lng: 69.2 });
    expect(toLatLng('41.3', '69.2')).toEqual({ lat: 41.3, lng: 69.2 });
  });

  it('is null when either half is missing or not a number', () => {
    expect(toLatLng(null, 69.2)).toBeNull();
    expect(toLatLng(41.3, undefined)).toBeNull();
    expect(toLatLng('x', 'y')).toBeNull();
  });

  it('is null for points that cannot be a place', () => {
    expect(toLatLng(91, 69.2)).toBeNull();
    expect(toLatLng(41.3, 181)).toBeNull();
    expect(toLatLng(0, 0)).toBeNull(); // an unset value, not Null Island
  });
});

describe('isInventedPoint', () => {
  it('recognises the points the old app made up for hand-typed addresses', () => {
    expect(isInventedPoint(40.7821, 72.3442)).toBe(true); // cart form default
    expect(isInventedPoint(41.31, 69.28)).toBe(true); // profile fallback
    expect(isInventedPoint(41.316755, 69.123402)).toBe(true); // cart fallback without branch coordinates
  });

  it('also when the numbers were saved as strings', () => {
    expect(isInventedPoint('40.7821', '72.3442')).toBe(true);
  });

  it('leaves real points alone, even close to an invented one', () => {
    expect(isInventedPoint(41.3651, 69.2872)).toBe(false);
    expect(isInventedPoint(41.3101, 69.28)).toBe(false); // ~100 m from the profile fallback
    expect(isInventedPoint(40.7821, 72.3443)).toBe(false);
  });

  it('is false for no point at all', () => {
    expect(isInventedPoint(null, null)).toBe(false);
    expect(isInventedPoint(undefined, undefined)).toBe(false);
  });
});

describe('isOnAnyBranch', () => {
  const branches = [
    { lat: 41.311081, lng: 69.240562 },
    { lat: 39.654388, lng: 66.975833 },
  ];

  it('flags a point copied from a branch', () => {
    expect(isOnAnyBranch({ lat: 39.654388, lng: 66.975833 }, branches)).toBe(true);
  });

  it('does not flag a point that merely is near a branch', () => {
    expect(isOnAnyBranch({ lat: 41.311181, lng: 69.240562 }, branches)).toBe(false); // ~11 m away
  });

  it('is false without branches', () => {
    expect(isOnAnyBranch({ lat: 41.311081, lng: 69.240562 }, [])).toBe(false);
  });
});
