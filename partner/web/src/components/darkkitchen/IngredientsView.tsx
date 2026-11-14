'use client';

import { useMemo, useState } from 'react';
import { Ban, Carrot, Link2, Pencil, Plus, Trash2, Undo2 } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, Dialog, EmptyState, Field, fieldClass, formatDateTime, InlineError, Notice, PageHeader, Switch } from '@rizqim/ui';
import { useOwnerCategories, useOwnerProducts } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from '../menu/access';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { describeError } from '../menu/serverError';
import { StockTabs } from '../menu/StockTabs';
import { DataTable, Pill, Td, Th } from '../reports/ui';
import type { Ingredient } from './api';
import { useDeleteIngredient, useIngredients, useLinkCategories, useReopenIngredient, useSaveIngredient, useSaveIngredientLinks, useStopIngredient } from './hooks';

// /stoplist/ingredients "Ingredientlar" (P6-55 / B9, module ingredient_stoplist): "Tovuq tugadi" — stopping an
// ingredient on a branch closes every dish critically linked to it at once, in every brand and on every channel; reopening
// brings them back (a dish stopped by its own entry stays stopped). The owner keeps the ingredients and which dishes use
// them (one by one or a whole category). Not recipes: no quantities.
export function IngredientsView() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const choice = useBranchChoice();

  return (
    <>
      <PageHeader title={t('dk_ingTitle')} subtitle={t('dk_ingSubtitle')} />
      <StockTabs current="ingredients" />
      {choice.isPending ? (
        <CardSkeletons count={2} lines={2} label={t('loading')} />
      ) : choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.branchId === null ? (
        <EmptyState icon={Carrot} title={t('menu_no_branches')} />
      ) : (
        <div className="flex flex-col gap-4">
          <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="max-w-xs" />
          <IngredientsBranch key={choice.branchId} branchId={choice.branchId} branchName={choice.branches.find((b) => b.id === choice.branchId)?.name ?? ''} canStop={access.can('stoplist.manage')} canEdit={access.can('menu.manage')} />
        </div>
      )}
    </>
  );
}

function IngredientsBranch({ branchId, branchName, canStop, canEdit }: { branchId: number; branchName: string; canStop: boolean; canEdit: boolean }) {
  const { t, language } = useI18n();
  const query = useIngredients(branchId);
  const [notice, setNotice] = useState<string | null>(null);
  const [stopping, setStopping] = useState<Ingredient | null>(null);
  const [editing, setEditing] = useState<Ingredient | 'new' | null>(null);
  const [linking, setLinking] = useState<Ingredient | null>(null);
  const [deleting, setDeleting] = useState<Ingredient | null>(null);
  const reopen = useReopenIngredient();
  const remove = useDeleteIngredient(() => {
    setDeleting(null);
    setNotice(t('dk_ingDeleted'));
  });

  if (query.isPending) return <CardSkeletons count={2} lines={2} label={t('loading')} />;
  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;

  const ingredients = query.data.ingredients;
  return (
    <div className="flex flex-col gap-4">
      <Notice message={notice} />
      {canEdit && (
        <div>
          <Button icon={Plus} onClick={() => setEditing('new')}>
            {t('dk_ingAdd')}
          </Button>
        </div>
      )}
      {reopen.error && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {describeError(reopen.error, t).message}
        </p>
      )}
      {ingredients.length === 0 ? (
        <EmptyState icon={Carrot} title={t('dk_ingEmpty')} text={t('dk_ingEmptyText')} />
      ) : (
        <Card>
          <DataTable caption={t('dk_ingTitle')}>
            <thead>
              <tr>
                <Th>{t('dk_ingredient')}</Th>
                <Th>{t('dk_ingDishes')}</Th>
                <Th>{t('dk_ingState')}</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {ingredients.map((ingredient) => {
                const here = ingredient.stopped.find((stop) => stop.branch_id === branchId);
                const critical = ingredient.links.filter((link) => link.is_critical).length;
                return (
                  <tr key={ingredient.id}>
                    <Td>
                      <span className="font-bold">{ingredient.name}</span>
                      {ingredient.unit && <span className="text-fg-muted"> · {ingredient.unit}</span>}
                    </Td>
                    <Td muted>{t('dk_ingDishesN', { n: critical, all: ingredient.links.length })}</Td>
                    <Td>
                      {here ? (
                        <span className="flex flex-col gap-1">
                          <Pill tone="danger">{here.reason || t('dk_ingOut', { name: ingredient.name })}</Pill>
                          {here.until && <span className="text-footnote text-fg-muted">{t('dk_ingUntil', { time: formatDateTime(here.until, language) })}</span>}
                        </span>
                      ) : (
                        <Pill tone="success">{t('dk_ingOn')}</Pill>
                      )}
                    </Td>
                    <Td>
                      <div className="flex flex-wrap justify-end gap-2">
                        {canStop &&
                          (here ? (
                            <Button
                              variant="secondary"
                              icon={Undo2}
                              loading={reopen.isPending && reopen.variables?.id === ingredient.id}
                              onClick={() => reopen.mutate({ id: ingredient.id, branchId }, { onSuccess: () => setNotice(t('dk_ingReopened', { name: ingredient.name })) })}
                            >
                              {t('dk_ingReopen')}
                            </Button>
                          ) : (
                            <Button variant="danger" icon={Ban} onClick={() => setStopping(ingredient)}>
                              {t('dk_ingStopNamed', { name: ingredient.name })}
                            </Button>
                          ))}
                        {canEdit && (
                          <>
                            <Button variant="secondary" icon={Link2} onClick={() => setLinking(ingredient)}>
                              {t('dk_ingLinks')}
                            </Button>
                            <Button variant="ghost" icon={Pencil} aria-label={t('dk_editNamed', { name: ingredient.name })} onClick={() => setEditing(ingredient)}>
                              {t('dk_edit')}
                            </Button>
                            <Button variant="ghost" icon={Trash2} aria-label={t('dk_deleteNamed', { name: ingredient.name })} onClick={() => setDeleting(ingredient)}>
                              {t('dk_delete')}
                            </Button>
                          </>
                        )}
                      </div>
                    </Td>
                  </tr>
                );
              })}
            </tbody>
          </DataTable>
        </Card>
      )}

      {stopping && (
        <StopDialog
          ingredient={stopping}
          branchId={branchId}
          branchName={branchName}
          onClose={() => setStopping(null)}
          onStopped={(closed) => {
            setNotice(t('dk_ingStopped', { name: stopping.name, n: closed }));
            setStopping(null);
          }}
        />
      )}
      {editing !== null && (
        <IngredientDialog
          ingredient={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            setNotice(t('dk_ingSaved'));
          }}
        />
      )}
      {linking && (
        <LinksDialog
          ingredient={linking}
          onClose={() => setLinking(null)}
          onSaved={(message) => {
            setLinking(null);
            setNotice(message);
          }}
        />
      )}
      {deleting && (
        <ConfirmDialog
          title={t('dk_ingDeleteTitle')}
          text={t('dk_ingDeleteText', { name: deleting.name })}
          confirmLabel={t('dk_delete')}
          cancelLabel={t('dk_cancel')}
          closeLabel={t('dk_close')}
          busy={remove.isPending}
          error={remove.error ? describeError(remove.error, t).message : null}
          onConfirm={() => remove.mutate(deleting.id)}
          onClose={() => setDeleting(null)}
        />
      )}
    </div>
  );
}

// "Tovuq tugadi": an optional reason and when it comes back by itself
function StopDialog({ ingredient, branchId, branchName, onClose, onStopped }: { ingredient: Ingredient; branchId: number; branchName: string; onClose: () => void; onStopped: (closed: number) => void }) {
  const { t } = useI18n();
  const [reason, setReason] = useState('');
  const [until, setUntil] = useState('');
  const stop = useStopIngredient(onStopped);
  const critical = ingredient.links.filter((link) => link.is_critical);
  // Checked when the stop is sent (the server refuses a past time too)
  const [past, setPast] = useState(false);

  return (
    <Dialog
      open
      onClose={onClose}
      busy={stop.isPending}
      closeLabel={t('dk_close')}
      title={t('dk_ingStopTitle', { name: ingredient.name })}
      description={t('dk_ingStopHint', { branch: branchName, n: critical.length })}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={stop.isPending}>
            {t('dk_cancel')}
          </Button>
          <Button
            variant="danger"
            icon={Ban}
            loading={stop.isPending}
            onClick={() => {
              const late = until !== '' && new Date(until).getTime() <= Date.now();
              setPast(late);
              if (!late) stop.mutate({ id: ingredient.id, branchId, reason: reason.trim() || null, until: until ? new Date(until).toISOString() : null });
            }}
          >
            {t('dk_ingStop')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        {critical.length > 0 ? (
          <p className="text-footnote text-fg">{critical.map((link) => link.name).join(', ')}</p>
        ) : (
          <p className="text-footnote font-bold text-warning-soft-fg">{t('dk_ingNoDishes')}</p>
        )}
        <Field label={t('dk_reason')} hint={t('dk_reasonHint', { name: ingredient.name })}>
          {(control) => <input {...control} className={fieldClass} value={reason} maxLength={255} onChange={(event) => setReason(event.target.value)} />}
        </Field>
        <Field label={t('dk_until')} hint={t('dk_untilHint')} error={past ? t('dk_untilPast') : undefined}>
          {(control) => <input {...control} type="datetime-local" className={fieldClass} value={until} onChange={(event) => {
                setUntil(event.target.value);
                setPast(false);
              }} />}
        </Field>
        {stop.error && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {describeError(stop.error, t).message}
          </p>
        )}
      </div>
    </Dialog>
  );
}

function IngredientDialog({ ingredient, onClose, onSaved }: { ingredient: Ingredient | null; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(ingredient?.name ?? '');
  const [unit, setUnit] = useState(ingredient?.unit ?? '');
  const save = useSaveIngredient(onSaved);

  return (
    <Dialog
      open
      onClose={onClose}
      busy={save.isPending}
      closeLabel={t('dk_close')}
      title={ingredient ? t('dk_ingEdit') : t('dk_ingAdd')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('dk_cancel')}
          </Button>
          <Button loading={save.isPending} disabled={name.trim() === ''} onClick={() => save.mutate({ id: ingredient?.id ?? null, name: name.trim(), unit: unit.trim() || null })}>
            {t('dk_save')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <Field label={t('dk_ingName')}>{(control) => <input {...control} className={fieldClass} value={name} maxLength={120} onChange={(event) => setName(event.target.value)} />}</Field>
        <Field label={t('dk_ingUnit')} hint={t('dk_ingUnitHint')}>
          {(control) => <input {...control} className={fieldClass} value={unit} maxLength={16} onChange={(event) => setUnit(event.target.value)} />}
        </Field>
        {save.error && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {describeError(save.error, t).message}
          </p>
        )}
      </div>
    </Dialog>
  );
}

// Which dishes use it: a tick per dish (critical or only marked), or a whole category at once
function LinksDialog({ ingredient, onClose, onSaved }: { ingredient: Ingredient; onClose: () => void; onSaved: (message: string) => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const categories = useOwnerCategories();
  const [links, setLinks] = useState(() => new Map(ingredient.links.map((link) => [link.product_id, link.is_critical])));
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const save = useSaveIngredientLinks(() => onSaved(t('dk_linksSaved')));
  const bulk = useLinkCategories((added) => onSaved(t('dk_linksAdded', { n: added })));
  const q = search.trim().toLowerCase();
  const list = useMemo(() => (products.data ?? []).filter((product) => q === '' || product.name.toLowerCase().includes(q)), [products.data, q]);
  const set = (id: number, value: boolean | null) =>
    setLinks((current) => {
      const next = new Map(current);
      if (value === null) next.delete(id);
      else next.set(id, value);
      return next;
    });
  const busy = save.isPending || bulk.isPending;

  return (
    <Dialog
      open
      onClose={onClose}
      busy={busy}
      closeLabel={t('dk_close')}
      title={t('dk_linksTitle', { name: ingredient.name })}
      description={t('dk_linksHint')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('dk_cancel')}
          </Button>
          <Button loading={save.isPending} disabled={busy} onClick={() => save.mutate({ id: ingredient.id, links: [...links].map(([product_id, is_critical]) => ({ product_id, is_critical })) })}>
            {t('dk_save')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <div className="flex flex-wrap items-end gap-2">
          <Field label={t('dk_linkCategory')} className="min-w-56">
            {(control) => (
              <select {...control} className={fieldClass} value={category} onChange={(event) => setCategory(event.target.value)}>
                <option value="">{t('dk_chooseCategory')}</option>
                {(categories.data ?? []).map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Button variant="secondary" loading={bulk.isPending} disabled={category === '' || busy} onClick={() => bulk.mutate({ id: ingredient.id, categoryIds: [Number(category)], isCritical: true })}>
            {t('dk_linkCategoryAdd')}
          </Button>
        </div>
        <Field label={t('dk_search')}>{(control) => <input {...control} className={fieldClass} value={search} onChange={(event) => setSearch(event.target.value)} />}</Field>
        <DataTable caption={t('dk_linksTitle', { name: ingredient.name })}>
          <thead>
            <tr>
              <Th>{t('dk_dish')}</Th>
              <Th>{t('dk_uses')}</Th>
              <Th>{t('dk_critical')}</Th>
            </tr>
          </thead>
          <tbody>
            {list.map((product) => {
              const linked = links.has(product.id);
              return (
                <tr key={product.id}>
                  <Td>{product.name}</Td>
                  <Td>
                    <input type="checkbox" className="size-5" aria-label={t('dk_usesNamed', { name: product.name })} checked={linked} onChange={(event) => set(product.id, event.target.checked ? true : null)} />
                  </Td>
                  <Td>
                    <Switch checked={links.get(product.id) ?? false} disabled={!linked} label={t('dk_criticalNamed', { name: product.name })} onChange={(value) => set(product.id, value)} />
                  </Td>
                </tr>
              );
            })}
          </tbody>
        </DataTable>
        {(save.error || bulk.error) && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {describeError(save.error ?? bulk.error, t).message}
          </p>
        )}
      </div>
    </Dialog>
  );
}
