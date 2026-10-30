import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import api from '../../services/api';
import { PickupTimePicker } from './PickupTimePicker';

vi.mock('../../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
const get = vi.mocked(api.get);

afterEach(() => vi.clearAllMocks());

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

// Slots at local noon today and tomorrow, so the wording does not depend on when or where the test runs
const at = (days: number, hour: number) => {
  const date = new Date();
  date.setDate(date.getDate() + days);
  date.setHours(hour, 0, 0, 0);
  return date.toISOString();
};
const dateOf = (iso: string) => iso.slice(0, 10);

const SLOT_TODAY = at(0, 23);
const SLOT_TOMORROW = at(1, 9);

describe('PickupTimePicker (P6-34)', () => {
  it("offers 'Hozir' and the branch's times, and hands back the slot chosen", async () => {
    get.mockResolvedValue({
      data: {
        success: true,
        data: {
          enabled: true,
          min_lead_minutes: 20,
          slot_minutes: 15,
          slots: [
            { at: SLOT_TODAY, time: '18:30', business_date: dateOf(SLOT_TODAY) },
            { at: SLOT_TOMORROW, time: '09:00', business_date: dateOf(SLOT_TOMORROW) },
          ],
        },
      },
    });
    const onChange = vi.fn();
    render(<PickupTimePicker storeId={4} branchId={7} value={null} onChange={onChange} />);
    await flush();

    expect(get).toHaveBeenCalledWith('/stores/4/branches/7/pickup-slots');
    const select = screen.getByRole('combobox', { name: 'Olib ketish vaqti' });
    expect([...select.querySelectorAll('option')].map((option) => option.textContent)).toEqual(["Hozir (tayyor bo'lishi bilan)", 'Bugun, 18:30', 'Ertaga, 09:00']);
    expect(screen.getByText('Eng erta: 20 daqiqadan keyin.')).toBeInTheDocument();

    fireEvent.change(select, { target: { value: SLOT_TODAY } });
    expect(onChange).toHaveBeenLastCalledWith(SLOT_TODAY, 'Bugun, 18:30');
    fireEvent.change(select, { target: { value: '' } });
    expect(onChange).toHaveBeenLastCalledWith(null, null);
  });

  it('shows nothing for a branch that takes no orders for later, or when the times cannot be read', async () => {
    get.mockResolvedValueOnce({ data: { success: true, data: { enabled: false, min_lead_minutes: 20, slot_minutes: 15, slots: [] } } });
    const { container, unmount } = render(<PickupTimePicker storeId={4} branchId={7} value={null} onChange={vi.fn()} />);
    await flush();
    expect(container).toBeEmptyDOMElement();
    unmount();

    get.mockRejectedValueOnce(new Error('offline'));
    const second = render(<PickupTimePicker storeId={4} branchId={7} value={null} onChange={vi.fn()} />);
    await flush();
    expect(second.container).toBeEmptyDOMElement();
  });

  it('drops a chosen time the branch no longer offers', async () => {
    get.mockResolvedValue({ data: { success: true, data: { enabled: true, min_lead_minutes: 20, slot_minutes: 15, slots: [{ at: '2026-10-22T14:00:00.000000Z', time: '19:00', business_date: dateOf(SLOT_TODAY) }] } } });
    const onChange = vi.fn();
    render(<PickupTimePicker storeId={4} branchId={7} value="2026-10-22T13:30:00.000000Z" onChange={onChange} />);
    await flush();
    expect(onChange).toHaveBeenCalledWith(null, null);
  });
});
