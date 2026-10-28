'use client';

import { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, CalendarClock, Image as ImageIcon, Pencil, Plus, Smartphone, Trash2 } from 'lucide-react';
import { Button, Card, IconButton, Notice, Pill, Section, Switch } from '@rizqim/ui';
import {
  BANNER_STATUSES,
  imageSrc,
  useBannerPreview,
  useBanners,
  useDeleteBanner,
  useOrderBanners,
  useUpdateBanner,
  type Banner,
  type BannerStatus,
} from '@/lib/api/marketing';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { BannerDialog } from './BannerDialog';
import { BANNER_LABEL, BANNER_TONE, moved, sameOrder } from './marketingModel';
import { Chip, ChipRow, ListState, ReasonDialog, Thumb } from './MarketingParts';

// Bannerlar va aksiyalar (P4-31): the banners of the Mini App's home screen. Reading needs marketing.view; adding, changing,
// switching on or off and ordering marketing.manage with a reason; deleting also the step-up. Ordering is done in the page
// (arrows) and saved as the whole row at once.

type Action = { kind: 'toggle'; banner: Banner } | { kind: 'delete'; banner: Banner } | { kind: 'order' };

export function BannersTab() {
  const t = useT();
  const { can } = useCan();
  const manage = can('marketing.manage');
  const [status, setStatus] = useState<BannerStatus | ''>('');
  const banners = useBanners(status);
  const [local, setLocal] = useState<number[] | null>(null);
  const [editing, setEditing] = useState<Banner | 'new' | null>(null);
  const [action, setAction] = useState<Action | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [showPreview, setShowPreview] = useState(false);

  const rows = useMemo(() => banners.data?.data ?? [], [banners.data]);
  const serverOrder = useMemo(() => rows.map((row) => row.id), [rows]);
  // A local order only counts while it holds the same banners as the server's list (else the list changed meanwhile)
  const order = local && local.length === serverOrder.length && local.every((id) => serverOrder.includes(id)) ? local : serverOrder;
  const dirty = !sameOrder(order, serverOrder);
  const shown = [...rows].sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
  // Only the whole row can be ordered
  const canOrder = manage && status === '';

  const move = (id: number, step: -1 | 1) => setLocal(moved(order, order.indexOf(id), step));
  const done = (message: string) => {
    setEditing(null);
    setAction(null);
    setLocal(null);
    setNotice(message);
  };

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <Notice message={notice} />
      <div className="flex flex-wrap items-center justify-between gap-2">
        <ChipRow label={t('marketing_banner_statusFilter')}>
          <Chip on={status === ''} onClick={() => setStatus('')}>
            {t('marketing_all')}
          </Chip>
          {BANNER_STATUSES.map((value) => (
            <Chip key={value} on={status === value} onClick={() => setStatus(value)}>
              {t(BANNER_LABEL[value])}
            </Chip>
          ))}
        </ChipRow>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" icon={Smartphone} aria-pressed={showPreview} onClick={() => setShowPreview((previous) => !previous)}>
            {t('marketing_banner_previewButton')}
          </Button>
          {manage && (
            <Button icon={Plus} onClick={() => setEditing('new')}>
              {t('marketing_banner_new')}
            </Button>
          )}
        </div>
      </div>

      {showPreview && <PreviewPanel />}

      {dirty && (
        <div role="status" className="flex flex-wrap items-center justify-between gap-2 rounded-control bg-info-soft px-3 py-2.5 text-footnote font-bold text-info-soft-fg">
          <span>{t('marketing_order_changed')}</span>
          <span className="flex gap-2">
            <Button variant="secondary" onClick={() => setLocal(null)}>
              {t('marketing_order_cancel')}
            </Button>
            <Button onClick={() => setAction({ kind: 'order' })}>{t('marketing_order_save')}</Button>
          </span>
        </div>
      )}

      <ListState
        loading={banners.isPending}
        error={banners.error}
        fetching={banners.isFetching}
        onRetry={() => void banners.refetch()}
        empty={rows.length === 0}
        emptyTitle={status ? t('marketing_banner_emptyFiltered') : t('marketing_banner_empty')}
        emptyText={status ? undefined : t('marketing_banner_emptyText')}
        icon={ImageIcon}
        errorTitle={t('marketing_loadError')}
      >
        <ul aria-label={t('marketing_banner_list')} className="flex flex-col gap-3">
          {shown.map((banner, index) => (
            <li key={banner.id}>
              <Card className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                  <Thumb path={banner.image_preview} label={t('marketing_banner_imageOf', { title: banner.title })} className="h-16 w-28" />
                  <div className="flex min-w-0 flex-col gap-1">
                    <h3 className="break-words text-body font-bold text-fg">{banner.title}</h3>
                    <div className="flex flex-wrap items-center gap-2">
                      <Pill tone={BANNER_TONE[banner.status]}>{t(BANNER_LABEL[banner.status])}</Pill>
                      {banner.store && <span className="text-footnote text-fg-muted">{t('marketing_banner_opensStore', { name: banner.store.name })}</span>}
                      {banner.product && <span className="text-footnote text-fg-muted">{t('marketing_banner_opensProduct', { name: banner.product.name })}</span>}
                    </div>
                    {(banner.starts_at || banner.ends_at) && (
                      <p className="flex items-center gap-1.5 text-footnote text-fg-muted">
                        <CalendarClock aria-hidden="true" className="size-4 shrink-0" />
                        {t('marketing_banner_schedule', { from: when(banner.starts_at, t('marketing_banner_now')), to: when(banner.ends_at, t('marketing_banner_noEnd')) })}
                      </p>
                    )}
                  </div>
                </div>
                {manage && (
                  <div className="flex flex-wrap items-center gap-1 sm:justify-end">
                    {canOrder && (
                      <>
                        <IconButton icon={ArrowUp} label={t('marketing_moveUp', { name: banner.title })} disabled={index === 0} onClick={() => move(banner.id, -1)} />
                        <IconButton icon={ArrowDown} label={t('marketing_moveDown', { name: banner.title })} disabled={index === shown.length - 1} onClick={() => move(banner.id, 1)} />
                      </>
                    )}
                    <Switch checked={banner.is_active} onChange={() => setAction({ kind: 'toggle', banner })} label={t('marketing_banner_toggle', { title: banner.title })} />
                    <IconButton icon={Pencil} label={t('marketing_banner_edit', { title: banner.title })} onClick={() => setEditing(banner)} />
                    <IconButton icon={Trash2} variant="danger" label={t('marketing_banner_delete', { title: banner.title })} onClick={() => setAction({ kind: 'delete', banner })} />
                  </div>
                )}
              </Card>
            </li>
          ))}
        </ul>
      </ListState>

      {editing && <BannerDialog banner={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onDone={done} />}
      {action?.kind === 'toggle' && <ToggleDialog banner={action.banner} onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'delete' && <DeleteDialog banner={action.banner} onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'order' && <OrderDialog ids={order} onClose={() => setAction(null)} onDone={done} />}
    </div>
  );
}

// What the Mini App's home screen shows right now, in its order
function PreviewPanel() {
  const t = useT();
  const preview = useBannerPreview();
  return (
    <Section title={t('marketing_banner_previewTitle')}>
      <Card className="flex flex-col gap-3">
        <p className="text-footnote text-fg-muted">{t('marketing_banner_previewText')}</p>
        {preview.isPending ? (
          <p className="text-footnote text-fg-muted">{t('loading')}</p>
        ) : preview.error ? (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {errorText(preview.error, t)}
          </p>
        ) : preview.data.data.length === 0 ? (
          <p className="text-footnote font-bold text-fg">{t('marketing_banner_previewEmpty')}</p>
        ) : (
          <ul aria-label={t('marketing_banner_previewList')} className="flex gap-3 overflow-x-auto pb-1">
            {preview.data.data.map((item) => (
              <li key={item.id} className="w-64 shrink-0">
                <figure className="flex flex-col gap-1.5">
                  {imageSrc(item.image_preview) ? (
                    // eslint-disable-next-line @next/next/no-img-element -- the picture comes through the session proxy
                    <img src={imageSrc(item.image_preview)} alt="" className="h-32 w-full rounded-card border border-line object-cover" />
                  ) : (
                    <Thumb path={null} label={item.title} className="h-32 w-full" />
                  )}
                  <figcaption className="text-footnote font-bold text-fg">{item.title}</figcaption>
                </figure>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}

function ToggleDialog({ banner, onDone, onClose }: { banner: Banner; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const update = useUpdateBanner();
  const turnOn = !banner.is_active;
  return (
    <ReasonDialog
      title={turnOn ? t('marketing_banner_onTitle') : t('marketing_banner_offTitle')}
      text={turnOn ? t('marketing_banner_onText', { title: banner.title }) : t('marketing_banner_offText', { title: banner.title })}
      submitLabel={turnOn ? t('marketing_turnOn') : t('marketing_turnOff')}
      busy={update.isPending}
      error={update.error ? errorText(update.error, t) : null}
      reasonError={fieldError(update.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => update.mutate({ id: banner.id, is_active: turnOn, reason }, { onSuccess: () => onDone(turnOn ? t('marketing_banner_turnedOn') : t('marketing_banner_turnedOff')) })}
    />
  );
}

function DeleteDialog({ banner, onDone, onClose }: { banner: Banner; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const remove = useDeleteBanner();
  return (
    <ReasonDialog
      title={t('marketing_banner_deleteTitle')}
      text={t('marketing_banner_deleteText', { title: banner.title })}
      submitLabel={t('marketing_delete')}
      tone="danger"
      busy={remove.isPending}
      error={remove.error ? errorText(remove.error, t) : null}
      reasonError={fieldError(remove.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => remove.mutate({ id: banner.id, reason }, { onSuccess: () => onDone(t('marketing_banner_deleted')) })}
    />
  );
}

function OrderDialog({ ids, onDone, onClose }: { ids: number[]; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const save = useOrderBanners();
  return (
    <ReasonDialog
      title={t('marketing_banner_orderTitle')}
      text={t('marketing_banner_orderText')}
      submitLabel={t('save')}
      busy={save.isPending}
      error={save.error ? errorText(save.error, t) : null}
      reasonError={fieldError(save.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => save.mutate({ ids, reason }, { onSuccess: () => onDone(t('marketing_order_saved')) })}
    />
  );
}

