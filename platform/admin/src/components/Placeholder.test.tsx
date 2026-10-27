import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { NAV_ITEMS } from '@/lib/nav';
import { I18nProvider } from './I18nProvider';
import { Placeholder } from './Placeholder';

describe('Placeholder', () => {
  it.each(NAV_ITEMS.filter((item) => !item.built))('says calmly that "$key" comes in the next wave', (item) => {
    render(
      <I18nProvider language="uz">
        <Placeholder title={item.label} />
      </I18nProvider>,
    );
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    expect(screen.getByText("Bu bo'lim keyingi to'lqinda qo'shiladi")).toBeInTheDocument();
  });
});
