import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SoldOutBadge } from './SoldOutBadge';

describe('SoldOutBadge', () => {
  it('says Tugadi, and when the next batch is planned (P6-15)', () => {
    const { rerender } = render(<SoldOutBadge />);
    expect(screen.getByText('Tugadi')).toBeInTheDocument();
    expect(screen.queryByText(/Keyingi partiya/)).not.toBeInTheDocument();

    rerender(<SoldOutBadge nextBatch="12:30" />);
    expect(screen.getByText('Tugadi')).toBeInTheDocument();
    expect(screen.getByText('Keyingi partiya 12:30')).toBeInTheDocument();
  });
});
