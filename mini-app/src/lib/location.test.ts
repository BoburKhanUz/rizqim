import { describe, expect, it } from 'vitest';
import { getLocationParams } from './location';

describe('getLocationParams', () => {
  it('sends everything the address knows', () => {
    expect(getLocationParams({ lat: 41.31, lng: 69.24, region_id: 1, district_id: 4 })).toEqual({
      lat: 41.31,
      lng: 69.24,
      region_id: 1,
      district_id: 4,
    });
  });

  it('leaves out what is missing, null or zero', () => {
    expect(getLocationParams({ lat: 41.31, lng: 69.24, region_id: null, district_id: undefined })).toEqual({
      lat: 41.31,
      lng: 69.24,
    });
    expect(getLocationParams({ lat: 0, lng: 0 })).toEqual({});
  });

  it('is empty without an address', () => {
    expect(getLocationParams(undefined)).toEqual({});
    expect(getLocationParams(null)).toEqual({});
  });
});
