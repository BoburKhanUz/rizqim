'use client';

import { useState } from 'react';
import { Button, Card, CardSkeletons, fieldClass, formatMoney, InlineError } from '@rizqim/ui';
import { useChannelPrices, useSaveChannelPrices, type ChannelPriceRow, type ChannelPrices as ChannelPricesData, type ChannelPricesInput, type Product } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ErrorBanner, SuccessNote } from './ErrorBanner';
import { parseDecimal, toInputText } from './numbers';
import { describeError } from './serverError';
import { Switch } from './Switch';

// The channels this screen sets. The save REPLACES every row of the product, so rows of other channels (POS, aggregators)
// are sent back as they were read.
const CHANNELS = ['telegram', 'mobile'] as const;
type Channel = (typeof CHANNELS)[number];
const isShown = (channel: string): channel is Channel => (CHANNELS as readonly string[]).includes(channel);

interface Cell {
  price: string;
  hidden: boolean;
}
const cellKey = (channel: Channel, variantId: number | null) => `${channel}:${variantId ?? 'product'}`;

export function ChannelPrices({ product, canManage }: { product: Product; canManage: boolean }) {
  const { t } = useI18n();
  const query = useChannelPrices(product.id);
  if (query.isPending) return <CardSkeletons count={1} lines={3} label={t('loading')} />;
  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  // Remounted when the rows are refetched (after a save), so the inputs show what the server stored
  return <ChannelPricesForm key={query.dataUpdatedAt} product={product} data={query.data} canManage={canManage} />;
}

function initialCells(rows: ChannelPriceRow[]): Record<string, Cell> {
  const cells: Record<string, Cell> = {};
  for (const row of rows) {
    if (!isShown(row.channel)) continue;
    cells[cellKey(row.channel, row.product_variant_id)] = { price: toInputText(row.price), hidden: row.is_hidden };
  }
  return cells;
}

function ChannelPricesForm({ product, data, canManage }: { product: Product; data: ChannelPricesData; canManage: boolean }) {
  const { t, language } = useI18n();
  const [cells, setCells] = useState(() => initialCells(data.prices));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [banner, setBanner] = useState<{ message: string; details: string[] } | null>(null);
  const [saved, setSaved] = useState(false);
  const save = useSaveChannelPrices();
  const variants = product.variants ?? [];

  const cell = (channel: Channel, variantId: number | null): Cell => cells[cellKey(channel, variantId)] ?? { price: '', hidden: false };
  const change = (channel: Channel, variantId: number | null, patch: Partial<Cell>) => {
    setSaved(false);
    setCells((current) => ({ ...current, [cellKey(channel, variantId)]: { ...cell(channel, variantId), ...patch } }));
  };

  const submit = () => {
    setSaved(false);
    const problems: Record<string, string> = {};
    const rows: ChannelPricesInput = data.prices
      .filter((row) => !isShown(row.channel))
      .map((row) => ({ channel: row.channel as Channel, product_variant_id: row.product_variant_id, price: row.price === null ? null : Number(row.price), is_hidden: row.is_hidden }));

    for (const channel of CHANNELS) {
      const whole = cell(channel, null);
      if (whole.hidden) rows.push({ channel, product_variant_id: null, is_hidden: true });
      for (const variant of variants) {
        const own = cell(channel, variant.id);
        const price = own.price.trim() === '' ? null : parseDecimal(own.price);
        if (own.price.trim() !== '' && price === null) problems[cellKey(channel, variant.id)] = t('menu_err_number');
        if (price !== null || own.hidden) rows.push({ channel, product_variant_id: variant.id, price, is_hidden: own.hidden });
      }
    }
    setErrors(problems);
    if (Object.keys(problems).length > 0) return setBanner({ message: t('menu_check_fields'), details: [] });
    setBanner(null);

    save.mutate(
      { productId: product.id, prices: rows },
      {
        onSuccess: () => setSaved(true),
        onError: (error) => {
          const view = describeError(error, t);
          setBanner({ message: view.message, details: Object.values(view.fields) });
        },
      },
    );
  };

  return (
    <div className="flex flex-col gap-3">
      <p className="text-footnote text-fg-muted">{t('menu_channels_hint')}</p>
      <fieldset disabled={!canManage || save.isPending} className="grid min-w-0 gap-3 border-0 p-0 lg:grid-cols-2">
        {CHANNELS.map((channel) => (
          <Card key={channel} role="group" aria-label={channel === 'telegram' ? t('menu_channel_telegram') : t('menu_channel_mobile')} className="flex flex-col gap-3">
            <h3 className="text-body font-bold text-fg">{channel === 'telegram' ? t('menu_channel_telegram') : t('menu_channel_mobile')}</h3>
            <div className="flex items-center gap-2">
              <Switch
                checked={cell(channel, null).hidden}
                onChange={(hidden) => change(channel, null, { hidden })}
                label={`${t('menu_hide_product')} (${channel === 'telegram' ? t('menu_channel_telegram') : t('menu_channel_mobile')})`}
              />
              <span className="text-body text-fg">{t('menu_hide_product')}</span>
            </div>
            <ul className="flex flex-col gap-3">
              {variants.map((variant) => {
                const own = cell(channel, variant.id);
                const label = `${variant.name ?? t('menu_variant_default')} (${channel === 'telegram' ? t('menu_channel_telegram') : t('menu_channel_mobile')})`;
                const error = errors[cellKey(channel, variant.id)];
                return (
                  <li key={variant.id} className="flex flex-col gap-1.5 border-t border-line pt-3">
                    <p className="text-body font-bold text-fg">{variant.name ?? t('menu_variant_default')}</p>
                    <p className="text-footnote text-fg-muted">{t('menu_base_price', { price: formatMoney(variant.price, language) })}</p>
                    <div className="flex flex-wrap items-center gap-3">
                      <input
                        aria-label={`${t('menu_channel_price')}: ${label}`}
                        aria-invalid={error ? true : undefined}
                        className={`${fieldClass} max-w-44`}
                        inputMode="decimal"
                        placeholder={t('menu_channel_price_placeholder')}
                        value={own.price}
                        onChange={(event) => change(channel, variant.id, { price: event.target.value })}
                      />
                      <span className="flex items-center gap-1">
                        <Switch checked={own.hidden} onChange={(hidden) => change(channel, variant.id, { hidden })} label={`${t('menu_hidden')}: ${label}`} />
                        <span className="text-body text-fg">{t('menu_hidden')}</span>
                      </span>
                    </div>
                    {error && (
                      <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
                        {error}
                      </p>
                    )}
                  </li>
                );
              })}
            </ul>
          </Card>
        ))}
      </fieldset>
      {banner && <ErrorBanner message={banner.message} details={banner.details} />}
      {saved && <SuccessNote>{t('menu_saved')}</SuccessNote>}
      {canManage && (
        <div>
          <Button onClick={submit} loading={save.isPending}>
            {t('menu_save_channel_prices')}
          </Button>
        </div>
      )}
    </div>
  );
}
