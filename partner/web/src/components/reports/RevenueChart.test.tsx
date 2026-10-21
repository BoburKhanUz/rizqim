import { fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { renderPage } from '@/test/helpers';
import { RevenueChart } from './RevenueChart';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

const day = (n: number, revenue: string, orders = 1) => ({ date: `2026-10-${String(n).padStart(2, '0')}`, orders, delivered: orders, revenue });

describe('RevenueChart', () => {
  it('scales the bars to the largest revenue and prints the server\'s figures', () => {
    renderPage(<RevenueChart series={[day(1, '100000.00'), day(2, '400000.00', 3), day(3, '0.00', 0)]} groupBy="day" />);
    const bars = within(screen.getByRole('group', { name: 'Tushum grafigi: 3 ta davr' })).getAllByRole('button');
    const heights = bars.map((bar) => (bar.firstElementChild as HTMLElement).style.height);
    expect(heights).toEqual(['25%', '100%', '0%']);
    fireEvent.focus(bars[1]!);
    expect(screen.getByText(/400 000 so'm · 3 ta buyurtma/)).toBeInTheDocument();
    fireEvent.blur(bars[1]!);
    expect(screen.getByText('Ustunni tanlang yoki ustiga olib boring')).toBeInTheDocument();
  });

  it('copes with a period of no revenue at all', () => {
    renderPage(<RevenueChart series={[day(1, '0.00', 0), day(2, '0.00', 0)]} groupBy="day" />);
    const bars = within(screen.getByRole('group', { name: 'Tushum grafigi: 2 ta davr' })).getAllByRole('button');
    expect(bars.map((bar) => (bar.firstElementChild as HTMLElement).style.height)).toEqual(['0%', '0%']);
  });

  it('labels about seven of many bars, and names a week or a month by its first day', () => {
    const series = Array.from({ length: 28 }, (_, n) => day(n + 1, '1000.00'));
    const { container } = renderPage(<RevenueChart series={series} groupBy="week" />);
    expect(container.querySelectorAll('[aria-hidden="true"] > span.absolute')).toHaveLength(7);
    expect(screen.getAllByRole('button', { name: /Hafta: 1-okt/ }).length).toBeGreaterThan(0);
  });
});
