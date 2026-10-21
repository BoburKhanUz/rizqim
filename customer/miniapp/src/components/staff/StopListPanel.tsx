import { useMemo, useState } from 'react';
import { PackageOpen, Search, SearchX } from 'lucide-react';
import {
  useAddStopListEntry,
  useBranchVariants,
  useModifierGroups,
  useRemoveStopListEntry,
  useStopList,
  type Localized,
  type StopListEntry,
} from '@rizqim/staff';
import { useLanguageStore } from '../../store/languageStore';
import { useRealtimeStore } from '../../store/realtimeStore';
import { hapticFeedback } from '../../lib/telegram';
import { notificationOccurred } from '../../lib/twa';
import { showAlert } from '../../lib/dialogs';
import { getApiErrorMessage, logApiError } from '../../lib/apiError';
import { formatDateTime } from '../../lib/format';
import { getPollInterval, POLL_INTERVAL_MS, pollingQueryOptions } from '../../lib/polling';
import { useDebouncedValue } from '../../hooks/useDebouncedValue';
import { useStaffText } from '../../hooks/useStaffText';
import { Card, CardSkeletons, EmptyState, InlineError, Section, cx, fieldClass } from '../ui';
import { Pill } from './Pill';
import { SegmentedControl } from './SegmentedControl';
import { Switch } from './Switch';

const TEXT = {
  search: { uz: "Taom yoki qo'shimcha qidirish", ru: 'Поиск блюда или добавки' },
  filter: { uz: "Ko'rinish", ru: 'Показать' },
  all: { uz: 'Hammasi', ru: 'Все' },
  stopped: { uz: "To'xtatilgan ({{n}})", ru: 'Остановлено ({{n}})' },
  dishes: { uz: 'Taomlar', ru: 'Блюда' },
  extras: { uz: "Qo'shimchalar", ru: 'Добавки' },
  onSale: { uz: 'Sotuvda: {{name}}', ru: 'В продаже: {{name}}' },
  stoppedBadge: { uz: 'Tugadi', ru: 'Закончилось' },
  parentStopped: { uz: "Taom to'xtatilgan", ru: 'Блюдо остановлено' },
  until: { uz: '{{time}} gacha', ru: 'До {{time}}' },
  noProducts: { uz: "Menyuda taom yo'q", ru: 'В меню нет блюд' },
  noProductsText: { uz: "Avval taom qo'shing: stop-list shu yerda ishlaydi.", ru: 'Сначала добавьте блюда: стоп-лист работает здесь.' },
  nothingFound: { uz: 'Hech narsa topilmadi', ru: 'Ничего не найдено' },
  nothingStopped: { uz: "Hozir hech narsa to'xtatilmagan", ru: 'Сейчас ничего не остановлено' },
  nothingStoppedText: { uz: 'Hamma taom sotuvda.', ru: 'Все блюда в продаже.' },
  failed: { uz: "Stop-listni o'zgartirib bo'lmadi", ru: 'Не удалось изменить стоп-лист' },
  loading: { uz: 'Stop-list yuklanmoqda', ru: 'Загрузка стоп-листа' },
} satisfies Record<string, Localized>;

type SubjectType = 'product' | 'variant' | 'modifier';
const keyOf = (type: SubjectType, id: number) => `${type}:${id}`;

// The switch of one thing on the stop-list: on = on sale, off = "tugadi"
function StopRow({
  name,
  caption,
  entry,
  disabled,
  indent = false,
  onToggle,
}: {
  name: string;
  caption?: string;
  entry: StopListEntry | undefined;
  disabled: boolean;
  indent?: boolean;
  onToggle: (onSale: boolean) => void;
}) {
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(TEXT);
  return (
    <div className={cx('flex items-center gap-2 py-0.5 pl-4 pr-2', indent && 'pl-8')}>
      <div className="min-w-0 flex-1 py-2">
        <p className={cx('truncate text-body', indent ? 'font-medium' : 'font-bold', entry ? 'text-fg-muted line-through' : 'text-fg')}>{name}</p>
        {caption && <p className="truncate text-footnote text-fg-muted">{caption}</p>}
        {entry && (
          <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-footnote text-danger-soft-fg">
            <Pill tone="danger">{tx('stoppedBadge')}</Pill>
            {entry.until && <span>{tx('until', { time: formatDateTime(entry.until, language) })}</span>}
            {entry.reason && <span className="text-fg-muted">{entry.reason}</span>}
          </p>
        )}
      </div>
      <Switch checked={!entry} onChange={onToggle} label={tx('onSale', { name })} disabled={disabled} />
    </div>
  );
}

// "Tugadi" with one tap: the dishes of the branch (and their variants) and the extras, searchable, each with a switch. The
// backend keeps the list (GET/POST/DELETE /owner/stop-list); the menu of the customers follows it at once.
export function StopListPanel({ branchId, ready }: { branchId: number | undefined; ready: boolean }) {
  const tx = useStaffText(TEXT);
  const realtimeConnected = useRealtimeStore((state) => state.connected);
  const [search, setSearch] = useState('');
  const [show, setShow] = useState<'all' | 'stopped'>('all');
  const [pending, setPending] = useState<string | null>(null);
  const query = useDebouncedValue(search.trim().toLowerCase(), 150);

  const polling = { ...pollingQueryOptions(getPollInterval(POLL_INTERVAL_MS.stopList, realtimeConnected)), enabled: ready };
  const stopList = useStopList(branchId, polling);
  const products = useBranchVariants(branchId, { enabled: ready });
  const groups = useModifierGroups({ enabled: ready });

  const afterChange = { onSettled: () => setPending(null) };
  const add = useAddStopListEntry({
    ...afterChange,
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => fail(err),
  });
  const remove = useRemoveStopListEntry({
    ...afterChange,
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => fail(err),
  });
  function fail(err: unknown) {
    logApiError('Failed to change the stop-list:', err);
    notificationOccurred('error');
    void showAlert(getApiErrorMessage(err) || tx('failed'));
  }

  const entries = useMemo(() => {
    const map = new Map<string, StopListEntry>();
    for (const entry of stopList.data ?? []) map.set(keyOf(entry.type as SubjectType, entry.subject_id), entry);
    return map;
  }, [stopList.data]);

  const toggle = (type: SubjectType, id: number, onSale: boolean) => {
    hapticFeedback('light');
    const key = keyOf(type, id);
    setPending(key);
    const entry = entries.get(key);
    if (onSale) {
      if (entry) remove.mutate(entry.id);
      else setPending(null);
    } else {
      add.mutate({ type, id, ...(branchId === undefined ? {} : { branch_id: branchId }) });
    }
  };

  const loadingFirst = !ready || ((stopList.isLoading || products.isLoading) && !stopList.data);
  if (loadingFirst) return <CardSkeletons count={3} lines={2} label={tx('loading')} />;
  if ((stopList.error && !stopList.data) || (products.error && !products.data)) {
    return <InlineError onRetry={() => void Promise.all([stopList.refetch(), products.refetch()])} retrying={stopList.isRefetching || products.isRefetching} />;
  }

  const stoppedCount = entries.size;
  const matches = (...texts: Array<string | null | undefined>) => query === '' || texts.some((text) => text?.toLowerCase().includes(query));
  const stoppedOnly = show === 'stopped';

  const dishRows = (products.data ?? [])
    .map((product) => {
      const productEntry = entries.get(keyOf('product', product.product_id));
      const variants = product.variants;
      const anyVariantStopped = variants.some((variant) => entries.has(keyOf('variant', variant.variant_id)));
      const visible =
        matches(product.name, ...variants.map((variant) => variant.name)) && (!stoppedOnly || productEntry !== undefined || anyVariantStopped);
      return { product, productEntry, variants, visible };
    })
    .filter((row) => row.visible);

  const extraRows = (groups.data ?? [])
    .map((group) => ({
      group,
      modifiers: group.modifiers.filter(
        (modifier) => modifier.is_active && matches(modifier.name, group.name) && (!stoppedOnly || entries.has(keyOf('modifier', modifier.id))),
      ),
    }))
    .filter((row) => row.modifiers.length > 0);

  const nothing = dishRows.length === 0 && extraRows.length === 0;

  return (
    <div className="flex flex-col gap-4">
      <div className="relative">
        <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
        <input
          type="search"
          aria-label={tx('search')}
          placeholder={tx('search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className={cx(fieldClass, 'bg-surface pl-10')}
        />
      </div>

      <SegmentedControl
        label={tx('filter')}
        value={show}
        onChange={setShow}
        segments={[
          { value: 'all', label: tx('all') },
          { value: 'stopped', label: tx('stopped', { n: stoppedCount }) },
        ]}
      />

      {nothing ? (
        stoppedOnly && query === '' ? (
          <EmptyState icon={PackageOpen} title={tx('nothingStopped')} text={tx('nothingStoppedText')} />
        ) : (products.data ?? []).length === 0 && query === '' ? (
          <EmptyState icon={PackageOpen} title={tx('noProducts')} text={tx('noProductsText')} />
        ) : (
          <EmptyState icon={SearchX} title={tx('nothingFound')} />
        )
      ) : (
        <>
          {dishRows.length > 0 && (
            <Section title={tx('dishes')}>
              <ul className="flex flex-col gap-3">
                {dishRows.map(({ product, productEntry, variants }) => (
                  <li key={product.product_id}>
                    <Card padded={false} className="divide-y divide-line overflow-hidden">
                      <StopRow
                        name={product.name}
                        entry={productEntry}
                        disabled={pending === keyOf('product', product.product_id)}
                        onToggle={(onSale) => toggle('product', product.product_id, onSale)}
                      />
                      {variants.length > 1 &&
                        variants.map((variant) => {
                          const entry = entries.get(keyOf('variant', variant.variant_id));
                          return (
                            <StopRow
                              key={variant.variant_id}
                              indent
                              name={variant.name ?? product.name}
                              caption={productEntry ? tx('parentStopped') : undefined}
                              entry={entry}
                              disabled={productEntry !== undefined || pending === keyOf('variant', variant.variant_id)}
                              onToggle={(onSale) => toggle('variant', variant.variant_id, onSale)}
                            />
                          );
                        })}
                    </Card>
                  </li>
                ))}
              </ul>
            </Section>
          )}
          {extraRows.length > 0 && (
            <Section title={tx('extras')}>
              <ul className="flex flex-col gap-3">
                {extraRows.map(({ group, modifiers }) => (
                  <li key={group.id}>
                    <Card padded={false} className="divide-y divide-line overflow-hidden">
                      {modifiers.map((modifier) => (
                        <StopRow
                          key={modifier.id}
                          name={modifier.name}
                          caption={group.name}
                          entry={entries.get(keyOf('modifier', modifier.id))}
                          disabled={pending === keyOf('modifier', modifier.id)}
                          onToggle={(onSale) => toggle('modifier', modifier.id, onSale)}
                        />
                      ))}
                    </Card>
                  </li>
                ))}
              </ul>
            </Section>
          )}
        </>
      )}
    </div>
  );
}
