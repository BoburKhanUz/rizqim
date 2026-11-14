'use client';

import type { Translate } from '@/i18n';
import type { Channel } from './api';

// The brand's short code on its colour, as the kitchen ticket and the label show it. The text is white or black,
// whichever reads better on the colour; the name (when given) follows in plain text.
export function BrandBadge({ code, color, label }: { code: string; color: string; label?: string }) {
  return (
    <span className="inline-flex items-center gap-2">
      <span className="inline-flex min-h-6 items-center rounded-control px-2 text-caption font-extrabold tracking-wide" style={{ backgroundColor: color, color: readableOn(color) }}>
        {code}
      </span>
      {label && <span className="text-body text-fg">{label}</span>}
    </span>
  );
}

// Black or white text on a #RRGGBB background (relative luminance)
export function readableOn(color: string): string {
  const hex = /^#([0-9a-f]{6})$/i.exec(color)?.[1];
  if (!hex) return '#FFFFFF';
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  const luminance = 0.2126 * (r ?? 0) + 0.7152 * (g ?? 0) + 0.0722 * (b ?? 0);
  return luminance > 0.4 ? '#000000' : '#FFFFFF';
}

export function channelLabel(channel: Channel | string, t: Translate): string {
  switch (channel) {
    case 'telegram':
      return t('dk_ch_telegram');
    case 'mobile':
      return t('dk_ch_mobile');
    case 'pos_hall':
      return t('dk_ch_pos_hall');
    case 'pos_takeaway':
      return t('dk_ch_pos_takeaway');
    case 'aggregator':
      return t('dk_ch_aggregator');
    default:
      return channel;
  }
}
