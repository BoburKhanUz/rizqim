import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { NAV_ITEMS } from '@/lib/nav';
import { I18nProvider } from './I18nProvider';
import { Placeholder } from './Placeholder';

describe('Placeholder', () => {
  // P4-31: every section is built now; the placeholder stays for a section added to the menu before its screen
  it('says calmly that a section comes in the next wave', () => {
    render(
      <I18nProvider language="uz">
        <Placeholder title="nav_integrations" />
      </I18nProvider>,
    );
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Integratsiyalar');
    expect(screen.getByText("Bu bo'lim keyingi to'lqinda qo'shiladi")).toBeInTheDocument();
  });

  it('is no longer shown by any section of the menu', () => {
    expect(NAV_ITEMS.filter((item) => !item.built).map((item) => item.key)).toEqual([]);
  });
});
