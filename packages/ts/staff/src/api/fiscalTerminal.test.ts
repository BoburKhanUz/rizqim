import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockFetch, ok, testApi } from '../test/helpers';
import { enterBankReport, fetchBankReports, parseBankRows, saveTerminalSetup } from './fiscalTerminal';

afterEach(() => vi.unstubAllGlobals());

describe('fiscal terminal flows (P6-16)', () => {
  it('asks for the mismatches only when told to, as a 1 the server reads as true', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/fiscal/bank-reports': () => ok([]) });
    await fetchBankReports(testApi(), { branch_id: 3, over_threshold: true });
    await fetchBankReports(testApi(), { over_threshold: false });
    expect(new URL(calls[0]!.url).search).toContain('branch_id=3');
    expect(new URL(calls[0]!.url).searchParams.get('over_threshold')).toBe('1');
    expect(new URL(calls[1]!.url).searchParams.has('over_threshold')).toBe(false);
  });

  it("sends a branch's setup and a shift's bank total as given", async () => {
    const { calls } = mockFetch({
      'PUT /api/v1/owner/branches/4/terminal-flow': () => ok({}),
      'PUT /api/v1/owner/fiscal/bank-reports/s-1': () => ok({}),
    });
    await saveTerminalSetup(testApi(), 4, { card_mode: 'smart_kassa', checklist: { scanner_2d: true } });
    await enterBankReport(testApi(), 's-1', { source: 'manual', bank_total: '1200000' });
    expect(await calls[0]!.json()).toEqual({ card_mode: 'smart_kassa', checklist: { scanner_2d: true } });
    expect(await calls[1]!.json()).toEqual({ source: 'manual', bank_total: '1200000' });
  });

  it('reads pasted bank rows', () => {
    expect(parseBankRows('000123;600000\n650000\nabc')).toEqual({ rows: [{ rrn: '000123', amount: '600000' }, { rrn: null, amount: '650000' }], bad: [3] });
  });
});
