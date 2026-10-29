'use client';

import { useState } from 'react';
import { CalendarCheck, Copy, Save } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, EmptyState, Field, fieldClass, formatDate, InlineError, Notice } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from '../menu/access';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { ErrorBanner } from '../menu/ErrorBanner';
import { describeError } from '../menu/serverError';
import { DateField } from '../pos/fields';
import { localDate } from '../reports/format';
import type { DailyMenu } from './api';
import { useCopyDailyMenu, useDailyMenu, useSaveDailyMenu } from './hooks';

// Bugungi menyu (stolovaya): the dishes the branch cooks on a business day. A dish of the daily rotation is on sale only
// while it is ticked here; a day with no menu sells everything (the page says so). The branch comes from the top bar.
export function DailyMenuPanel() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const choice = useBranchChoice();
  const [date, setDate] = useState('');
  const canEdit = access.can('menu.manage') || access.can('stoplist.manage');

  if (choice.isPending) return <CardSkeletons count={3} lines={2} label={t('loading')} />;
  if (choice.isError) return <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />;
  if (choice.branchId === null) return <EmptyState icon={CalendarCheck} title={t('menu_no_branches')} />;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-4">
        <BranchSelect branches={choice.branches} value={choice.branchId} onChange={(id) => { choice.choose(id); setDate(''); }} className="min-w-52 max-w-xs" />
      </div>
      <MenuOfDay key={choice.branchId} branchId={choice.branchId} date={date} onDate={setDate} canEdit={canEdit} />
    </div>
  );
}

function MenuOfDay({ branchId, date, onDate, canEdit }: { branchId: number; date: string; onDate: (date: string) => void; canEdit: boolean }) {
  const { t } = useI18n();
  const query = useDailyMenu(branchId, date);
  // The answer to a save or copy lives here: the editor below starts again from the server's menu after it (its key), which
  // would drop the message the moment it appears
  const [saved, setSaved] = useState<string | null>(null);

  if (query.isPending) return <CardSkeletons count={3} lines={3} label={t('loading')} />;
  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;

  const menu = query.data;
  // The editor starts again from the server's menu after every save, copy or change of day
  const key = [menu.date, menu.product_ids.join(','), menu.products.map((product) => (product.daily_rotation ? 1 : 0)).join('')].join('|');
  return <MenuEditor key={key} menu={menu} onDate={(next) => { setSaved(null); onDate(next); }} canEdit={canEdit} saved={saved} onSaved={setSaved} />;
}

function plusDays(day: string, days: number): string {
  const date = localDate(day);
  date.setDate(date.getDate() + days);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function MenuEditor({ menu, onDate, canEdit, saved, onSaved }: { menu: DailyMenu; onDate: (date: string) => void; canEdit: boolean; saved: string | null; onSaved: (message: string | null) => void }) {
  const { t, language } = useI18n();
  const [selected, setSelected] = useState<Set<number>>(() => new Set(menu.product_ids));
  // The dishes that rotate (sold only on a day they are ticked); starts as the server's list
  const [rotating, setRotating] = useState<Set<number>>(() => new Set(menu.products.filter((product) => product.daily_rotation).map((product) => product.id)));
  const [search, setSearch] = useState('');
  const [confirmCopy, setConfirmCopy] = useState(false);

  const save = useSaveDailyMenu({ onSuccess: () => onSaved(t('dm_saved')) });
  const copy = useCopyDailyMenu({
    onSuccess: () => {
      setConfirmCopy(false);
      onSaved(t('dm_copied'));
    },
  });
  const failure = save.error ? describeError(save.error, t) : copy.error ? describeError(copy.error, t) : null;

  const needle = search.trim().toLowerCase();
  const matches = (name: string) => needle === '' || name.toLowerCase().includes(needle);
  const daily = menu.products.filter((product) => rotating.has(product.id) && matches(product.name));
  const permanent = menu.products.filter((product) => !rotating.has(product.id) && matches(product.name));

  const byCategory = new Map<string, typeof daily>();
  for (const product of daily) {
    const name = product.category_name ?? '—';
    byCategory.set(name, [...(byCategory.get(name) ?? []), product]);
  }
  const groups = [...byCategory.entries()];

  const toggle = (id: number) =>
    setSelected((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const makeDaily = (id: number) => {
    setRotating((current) => new Set(current).add(id));
    setSelected((current) => new Set(current).add(id));
  };
  const makePermanent = (id: number) => {
    setRotating((current) => {
      const next = new Set(current);
      next.delete(id);
      return next;
    });
    setSelected((current) => {
      const next = new Set(current);
      next.delete(id);
      return next;
    });
  };

  // Which dishes rotate is saved too, when the owner changed it here
  const rotationChanged = menu.products.some((product) => rotating.has(product.id) !== product.daily_rotation);
  const submit = () => {
    onSaved(null);
    // In the order of the list, so the menu reads as the owner ticked it
    const ordered = menu.products.filter((product) => selected.has(product.id) && rotating.has(product.id)).map((product) => product.id);
    save.mutate({ branchId: menu.branch_id, date: menu.date, productIds: ordered, ...(rotationChanged ? { rotation: [...rotating] } : {}) });
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-4">
        <DateField label={t('dm_date')} value={menu.date} min={menu.business_date} max={plusDays(menu.business_date, 30)} onChange={(value) => value !== '' && onDate(value)} />
        <p className="pb-3 text-footnote text-fg-muted">{menu.date === menu.business_date ? t('dm_today') : formatDate(localDate(menu.date), language)}</p>
        {canEdit && (
          <div className="flex flex-wrap gap-2 sm:ml-auto">
            <Button
              variant="secondary"
              icon={Copy}
              disabled={menu.previous_date === null}
              onClick={() => (menu.set ? setConfirmCopy(true) : copy.mutate({ branchId: menu.branch_id, date: menu.date, fromDate: menu.previous_date ?? undefined }))}
              loading={copy.isPending && !confirmCopy}
            >
              {menu.previous_date ? t('dm_copyFrom', { date: formatDate(localDate(menu.previous_date), language) }) : t('dm_copy')}
            </Button>
            <Button icon={Save} loading={save.isPending} onClick={submit}>
              {t('dm_save')}
            </Button>
          </div>
        )}
      </div>

      {!menu.set && <p className="rounded-control bg-warning-soft px-3 py-2.5 text-footnote font-bold text-warning-soft-fg">{t('dm_notSet')}</p>}
      <Notice message={saved} />
      {failure && !confirmCopy && <ErrorBanner message={failure.message} />}

      <div className="flex flex-wrap items-end gap-4">
        <Field label={t('dm_search')} className="min-w-52 max-w-xs">
          {(control) => <input {...control} className={fieldClass} value={search} onChange={(event) => setSearch(event.target.value)} />}
        </Field>
        <p className="pb-3 text-body font-bold text-fg">{t('dm_selected', { n: [...selected].filter((id) => rotating.has(id)).length })}</p>
      </div>

      {menu.products.length === 0 ? (
        <EmptyState icon={CalendarCheck} title={t('dm_noDishes')} text={t('dm_noDishesText')} />
      ) : (
        <>
          {groups.length === 0 && daily.length === 0 && permanent.length === 0 && <p className="text-footnote text-fg-muted">{t('dm_nothingFound')}</p>}
          {groups.map(([category, products]) => (
            <Card key={category} className="flex flex-col gap-1">
              <h2 className="text-headline font-bold text-fg">{category}</h2>
              <ul>
                {products.map((product) => (
                  <li key={product.id} className="flex min-h-11 flex-wrap items-center gap-3 border-b border-line py-1 last:border-b-0">
                    <label className="flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-3">
                      <input type="checkbox" className="size-5 shrink-0 accent-[var(--color-brand)]" checked={selected.has(product.id)} disabled={!canEdit} onChange={() => toggle(product.id)} />
                      <span className="min-w-0 text-body text-fg">
                        {product.name}
                        {product.is_set && <span className="ml-2 rounded-control bg-surface-2 px-2 py-0.5 text-caption font-bold text-fg-muted">{t('dm_setBadge')}</span>}
                      </span>
                    </label>
                    {canEdit && (
                      <Button variant="ghost" onClick={() => makePermanent(product.id)}>
                        {t('dm_makePermanent')}
                        <span className="sr-only"> {product.name}</span>
                      </Button>
                    )}
                  </li>
                ))}
              </ul>
            </Card>
          ))}

          {permanent.length > 0 && (
            <Card className="flex flex-col gap-1">
              <h2 className="text-headline font-bold text-fg">{t('dm_permanentTitle')}</h2>
              <p className="text-footnote text-fg-muted">{t('dm_permanentText')}</p>
              <ul>
                {permanent.map((product) => (
                  <li key={product.id} className="flex min-h-11 flex-wrap items-center gap-3 border-b border-line py-1 last:border-b-0">
                    <span className="min-w-0 flex-1 text-body text-fg">{product.name}</span>
                    {canEdit && (
                      <Button variant="ghost" onClick={() => makeDaily(product.id)}>
                        {t('dm_makeDaily')}
                        <span className="sr-only"> {product.name}</span>
                      </Button>
                    )}
                  </li>
                ))}
              </ul>
            </Card>
          )}
        </>
      )}

      {confirmCopy && (
        <ConfirmDialog
          title={t('dm_copyTitle')}
          text={t('dm_copyText', { date: menu.previous_date ? formatDate(localDate(menu.previous_date), language) : '' })}
          confirmLabel={t('dm_copyConfirm')}
          cancelLabel={t('menu_cancel')}
          closeLabel={t('menu_close')}
          busy={copy.isPending}
          error={failure?.message ?? null}
          tone="primary"
          onConfirm={() => copy.mutate({ branchId: menu.branch_id, date: menu.date, fromDate: menu.previous_date ?? undefined })}
          onClose={() => setConfirmCopy(false)}
        />
      )}
    </div>
  );
}
