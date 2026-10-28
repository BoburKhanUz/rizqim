import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, renderPage } from '@/test/helpers';
import { StorePicker } from './StorePicker';

function Picker({ onPick }: { onPick: (id: number | null) => void }) {
  const [value, setValue] = useState<number | null>(null);
  return (
    <StorePicker
      value={value}
      onChange={(id) => {
        setValue(id);
        onPick(id);
      }}
    />
  );
}

describe('StorePicker', () => {
  it('finds stores by name, picks one with the keyboard and clears it', async () => {
    const onPick = vi.fn();
    const { callsTo } = mockFetch({
      'GET /auth/me': () => json(meBody()),
      'GET /stores/lookup': (request) => {
        const url = new URL(request.url);
        return url.searchParams.get('ids[]')
          ? ok({ stores: [{ id: 12, name: 'Lazzat Oshxona', status: 'active', region: 'Toshkent shahri' }], regions: [], districts: [] })
          : ok({ stores: [{ id: 12, name: 'Lazzat Oshxona', status: 'active', region: 'Toshkent shahri' }, { id: 14, name: 'Lazzat Burger', status: 'active', region: null }], regions: [], districts: [] });
      },
    });
    renderPage(<Picker onPick={onPick} />);
    const user = userEvent.setup();
    const box = screen.getByRole('combobox', { name: 'Ovqatxona' });
    await user.type(box, 'lazz');
    await vi.waitFor(() => expect(callsTo('GET', '/stores/lookup').some((call) => call.query.get('q') === 'lazz')).toBe(true));
    expect(await screen.findByRole('option', { name: /Lazzat Burger/ })).toBeInTheDocument();
    await user.keyboard('{ArrowDown}{Enter}');
    expect(onPick).toHaveBeenCalledWith(14);
    await user.click(screen.getByRole('button', { name: 'Tanlovni olib tashlash' }));
    expect(onPick).toHaveBeenLastCalledWith(null);
    expect(screen.getByRole('combobox', { name: 'Ovqatxona' })).toBeInTheDocument();
  });
});
