'use client';

import { Volume2, VolumeX } from 'lucide-react';
import { cx, IconButton } from '@rizqim/ui';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { BranchSwitcher } from './BranchScope';
import { POLL_MS, useLiveOrders } from './LiveOrders';

// The bar above every page: the branch picker, and for people who see orders the live status and the sound switch.
export function TopBar() {
  const t = useT();
  const seesOrders = useCan('orders.view');
  const { realtime, muted, toggleMuted, needsGesture } = useLiveOrders();

  return (
    <div className="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line pb-3 md:mb-6">
      <BranchSwitcher />
      {seesOrders && (
        <div className="ml-auto flex flex-wrap items-center justify-end gap-2">
          {needsGesture && (
            <p role="status" className="max-w-xs text-caption text-fg-muted">
              {t('soundNeedsClick')}
            </p>
          )}
          <LiveStatus status={realtime} label={realtime === 'live' ? t('liveOn') : realtime === 'connecting' ? t('liveConnecting') : t('livePolling', { seconds: POLL_MS / 1000 })} hint={realtime === 'live' ? undefined : t('liveHint')} />
          <IconButton
            icon={muted ? VolumeX : Volume2}
            label={muted ? t('soundUnmute') : t('soundMute')}
            aria-pressed={!muted}
            variant="surface"
            onClick={toggleMuted}
          />
        </div>
      )}
    </div>
  );
}

function LiveStatus({ status, label, hint }: { status: string; label: string; hint?: string }) {
  return (
    <span
      title={hint}
      data-status={status}
      className="inline-flex min-h-8 items-center gap-2 rounded-full border border-line bg-surface px-3 text-caption font-medium text-fg"
    >
      <span aria-hidden="true" className={cx('size-2.5 rounded-full', status === 'live' ? 'bg-success' : status === 'connecting' ? 'bg-warning' : 'bg-fg-subtle')} />
      {label}
    </span>
  );
}
