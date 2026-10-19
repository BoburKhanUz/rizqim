import { fireEvent, render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

// The Telegram WebApp object, replaced per test
const { telegram } = vi.hoisted(() => ({ telegram: { tg: {} as Record<string, unknown> } }));
vi.mock('../../lib/telegram', () => telegram);

import MapPicker from './MapPicker';

// The real Leaflet map in jsdom. What is under test is which clicks inside the map the Mini App takes over:
// the links of the attribution (they must leave the WebView) and nothing else (Leaflet's own controls are <a href="#">).

afterEach(() => {
  telegram.tg = {};
});

function renderMap(tg: Record<string, unknown>) {
  telegram.tg = tg;
  const { container } = render(<MapPicker position={null} onPick={vi.fn()} />);
  return (selector: string): HTMLElement => {
    const element = container.querySelector<HTMLElement>(selector);
    if (!element) throw new Error(`Leaflet rendered nothing for ${selector}`);
    return element;
  };
}

describe('MapPicker: clicks on links inside the map', () => {
  it.each(['.leaflet-control-zoom-in', '.leaflet-control-zoom-out'])('does not send the %s button out to the browser', (selector) => {
    const openLink = vi.fn();
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    const find = renderMap({ openLink });
    const button = find(selector);
    expect(button.tagName).toBe('A');
    expect(button.getAttribute('href')).toBe('#'); // what used to open "<app-url>#" in the external browser

    fireEvent.click(button);

    expect(openLink).not.toHaveBeenCalled();
    expect(open).not.toHaveBeenCalled();
  });

  it('leaves the click on a zoom button untouched until Leaflet has handled it', () => {
    const find = renderMap({ openLink: vi.fn() });
    // Seen on the way down to the button, i.e. after the map wrapper had its capture-phase turn
    let preventedBeforeLeaflet: boolean | null = null;
    find('.leaflet-control-zoom').addEventListener('click', (event) => { preventedBeforeLeaflet = event.defaultPrevented; }, { capture: true });

    fireEvent.click(find('.leaflet-control-zoom-in'));

    expect(preventedBeforeLeaflet).toBe(false);
  });

  it("opens the attribution's links through Telegram, not in the Mini App's own WebView", () => {
    const openLink = vi.fn();
    const find = renderMap({ openLink });

    const notPrevented = fireEvent.click(find('.leaflet-control-attribution a[href*="openstreetmap.org"]'));

    expect(notPrevented).toBe(false); // the in-WebView navigation was cancelled
    expect(openLink).toHaveBeenCalledTimes(1);
    expect(openLink).toHaveBeenCalledWith('https://www.openstreetmap.org/copyright');
  });

  it("takes over Leaflet's own credit link in the attribution too", () => {
    const openLink = vi.fn();
    const find = renderMap({ openLink });

    fireEvent.click(find('.leaflet-control-attribution a[href*="leafletjs.com"]'));

    expect(openLink).toHaveBeenCalledTimes(1);
    expect(openLink.mock.calls[0][0]).toMatch(/^https:\/\/leafletjs\.com/);
  });

  it('opens an attribution link in a new window when Telegram cannot (plain browser)', () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    const find = renderMap({});

    fireEvent.click(find('.leaflet-control-attribution a[href*="openstreetmap.org"]'));

    expect(open).toHaveBeenCalledWith('https://www.openstreetmap.org/copyright', '_blank', 'noopener');
  });
});
