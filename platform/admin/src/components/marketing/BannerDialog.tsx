'use client';

import { useState } from 'react';
import { Field, fieldClass, Switch } from '@rizqim/ui';
import { useCreateBanner, useUpdateBanner, type Banner, type BannerInput } from '@/lib/api/marketing';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { fromLocalInput, toLocalInput } from './marketingModel';
import { ImageField, LookupPicker, ReasonDialog } from './MarketingParts';

// A new banner or the change of one (marketing.manage + a reason). The picture is required for a new banner; a new file on an
// existing one replaces the old. The schedule is in Tashkent time; both ends empty = always (while switched on).

export function BannerDialog({ banner, onDone, onClose }: { banner: Banner | null; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const create = useCreateBanner();
  const update = useUpdateBanner();
  const mutation = banner ? update : create;

  const [title, setTitle] = useState(banner?.title ?? '');
  const [image, setImage] = useState<File | null>(null);
  const [store, setStore] = useState(banner?.store ?? null);
  const [product, setProduct] = useState(banner?.product ? { id: banner.product.id, name: banner.product.name } : null);
  const [active, setActive] = useState(banner?.is_active ?? true);
  const [startsAt, setStartsAt] = useState(toLocalInput(banner?.starts_at));
  const [endsAt, setEndsAt] = useState(toLocalInput(banner?.ends_at));

  const starts = fromLocalInput(startsAt);
  const ends = fromLocalInput(endsAt);
  const scheduleBad = starts !== null && ends !== null && new Date(ends).getTime() <= new Date(starts).getTime();
  const valid = title.trim().length >= 2 && (banner !== null || image !== null) && !scheduleBad;

  const changes = (): Omit<BannerInput, 'reason'> => {
    if (!banner) return { title: title.trim(), image, store_id: store?.id ?? null, product_id: product?.id ?? null, is_active: active, starts_at: starts, ends_at: ends };
    const result: Omit<BannerInput, 'reason'> = {};
    if (title.trim() !== banner.title) result.title = title.trim();
    if (image) result.image = image;
    if ((store?.id ?? null) !== (banner.store?.id ?? null)) result.store_id = store?.id ?? null;
    if ((product?.id ?? null) !== (banner.product?.id ?? null)) result.product_id = product?.id ?? null;
    if (active !== banner.is_active) result.is_active = active;
    if (starts !== (banner.starts_at ? fromLocalInput(toLocalInput(banner.starts_at)) : null)) result.starts_at = starts;
    if (ends !== (banner.ends_at ? fromLocalInput(toLocalInput(banner.ends_at)) : null)) result.ends_at = ends;
    return result;
  };
  const changed = banner === null || Object.keys(changes()).length > 0;

  const submit = (reason: string) => {
    const body = { ...changes(), reason };
    if (banner) update.mutate({ id: banner.id, ...body }, { onSuccess: () => onDone(t('marketing_banner_saved')) });
    else create.mutate({ ...body, title: title.trim(), image: image as File }, { onSuccess: () => onDone(t('marketing_banner_created')) });
  };

  return (
    <ReasonDialog
      title={banner ? t('marketing_banner_editTitle') : t('marketing_banner_newTitle')}
      text={t('marketing_banner_dialogText')}
      submitLabel={t('save')}
      busy={mutation.isPending}
      error={mutation.error ? errorText(mutation.error, t) : null}
      reasonError={fieldError(mutation.error, 'reason')}
      canSubmit={valid && changed}
      onClose={onClose}
      onSubmit={submit}
    >
      <Field label={t('marketing_banner_title')} error={fieldError(mutation.error, 'title')}>
        {(control) => <input {...control} data-autofocus type="text" className={fieldClass} value={title} maxLength={255} onChange={(event) => setTitle(event.target.value)} />}
      </Field>
      <ImageField
        label={t('marketing_banner_image')}
        hint={t('marketing_imageHint')}
        current={banner?.image_preview}
        currentLabel={banner ? t('marketing_banner_imageOf', { title: banner.title }) : undefined}
        file={image}
        onFile={setImage}
        error={fieldError(mutation.error, 'image')}
        required={banner === null}
      />
      <LookupPicker
        label={t('marketing_banner_store')}
        kind="store"
        value={store}
        onChange={(next) => {
          setStore(next);
          // A product belongs to one store
          if (next?.id !== store?.id) setProduct(null);
        }}
        hint={t('marketing_banner_storeHint')}
      />
      <LookupPicker label={t('marketing_banner_product')} kind="product" storeId={store?.id} value={product} onChange={setProduct} />
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={t('marketing_banner_from')} hint={t('marketing_banner_scheduleHint')} error={fieldError(mutation.error, 'starts_at')}>
          {(control) => <input {...control} type="datetime-local" className={fieldClass} value={startsAt} onChange={(event) => setStartsAt(event.target.value)} />}
        </Field>
        <Field label={t('marketing_banner_to')} error={scheduleBad ? t('marketing_banner_scheduleBad') : fieldError(mutation.error, 'ends_at')}>
          {(control) => <input {...control} type="datetime-local" className={fieldClass} value={endsAt} onChange={(event) => setEndsAt(event.target.value)} />}
        </Field>
      </div>
      <Switch checked={active} onChange={setActive} label={t('marketing_banner_active')} />
    </ReasonDialog>
  );
}
