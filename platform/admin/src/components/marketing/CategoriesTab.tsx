'use client';

import { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, Layers, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, Card, IconButton, Notice, Pill, Switch } from '@rizqim/ui';
import { useCategories, useDeleteCategory, useOrderCategories, useUpdateCategory, type Category } from '@/lib/api/marketing';
import { errorText, fieldError } from '@/lib/errors';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { CategoryDialog } from './CategoryDialog';
import { moved, sameOrder } from './marketingModel';
import { ListState, ReasonDialog, Thumb } from './MarketingParts';

// Kategoriyalar (P4-31): the categories of the marketplace in the order the Mini App shows them. Reading needs marketing.view;
// adding, changing, hiding and ordering marketing.manage with a reason; deleting (only an empty category) also the step-up.
// Ordering is done in the page (arrows) and saved as the whole list at once.

type Action = { kind: 'toggle'; category: Category } | { kind: 'delete'; category: Category } | { kind: 'order' };

export function CategoriesTab() {
  const t = useT();
  const { can } = useCan();
  const manage = can('marketing.manage');
  const categories = useCategories();
  const [local, setLocal] = useState<number[] | null>(null);
  const [editing, setEditing] = useState<Category | 'new' | null>(null);
  const [action, setAction] = useState<Action | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const rows = useMemo(() => categories.data?.data ?? [], [categories.data]);
  const serverOrder = useMemo(() => rows.map((row) => row.id), [rows]);
  const order = local && local.length === serverOrder.length && local.every((id) => serverOrder.includes(id)) ? local : serverOrder;
  const dirty = !sameOrder(order, serverOrder);
  const shown = [...rows].sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));

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
        <p className="text-footnote text-fg-muted">{t('marketing_category_intro')}</p>
        {manage && (
          <Button icon={Plus} onClick={() => setEditing('new')}>
            {t('marketing_category_new')}
          </Button>
        )}
      </div>

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
        loading={categories.isPending}
        error={categories.error}
        fetching={categories.isFetching}
        onRetry={() => void categories.refetch()}
        empty={rows.length === 0}
        emptyTitle={t('marketing_category_empty')}
        emptyText={t('marketing_category_emptyText')}
        icon={Layers}
        errorTitle={t('marketing_loadError')}
      >
        <ul aria-label={t('marketing_category_list')} className="flex flex-col gap-3">
          {shown.map((category, index) => (
            <li key={category.id}>
              <Card className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="flex min-w-0 flex-1 items-center gap-3">
                  <span className="w-6 shrink-0 text-center text-footnote font-bold text-fg-muted" aria-hidden="true">
                    {index + 1}
                  </span>
                  <Thumb path={category.image_preview} label={t('marketing_category_iconOf', { name: category.name })} className="size-12" />
                  <div className="flex min-w-0 flex-col gap-1">
                    <h3 className="break-words text-body font-bold text-fg">{category.name}</h3>
                    <div className="flex flex-wrap items-center gap-2">
                      <Pill tone={category.is_active ? 'success' : 'neutral'}>{category.is_active ? t('marketing_category_visible') : t('marketing_category_hidden')}</Pill>
                      {category.parent && <span className="text-footnote text-fg-muted">{t('marketing_category_in', { name: category.parent.name })}</span>}
                      <span className="text-footnote text-fg-muted">{t('marketing_category_products', { count: category.products_count })}</span>
                      {category.children_count > 0 && <span className="text-footnote text-fg-muted">{t('marketing_category_children', { count: category.children_count })}</span>}
                    </div>
                  </div>
                </div>
                {manage && (
                  <div className="flex flex-wrap items-center gap-1 sm:justify-end">
                    <IconButton icon={ArrowUp} label={t('marketing_moveUp', { name: category.name })} disabled={index === 0} onClick={() => move(category.id, -1)} />
                    <IconButton icon={ArrowDown} label={t('marketing_moveDown', { name: category.name })} disabled={index === shown.length - 1} onClick={() => move(category.id, 1)} />
                    <Switch checked={category.is_active} onChange={() => setAction({ kind: 'toggle', category })} label={t('marketing_category_toggle', { name: category.name })} />
                    <IconButton icon={Pencil} label={t('marketing_category_edit', { name: category.name })} onClick={() => setEditing(category)} />
                    {category.products_count === 0 && category.children_count === 0 && (
                      <IconButton icon={Trash2} variant="danger" label={t('marketing_category_delete', { name: category.name })} onClick={() => setAction({ kind: 'delete', category })} />
                    )}
                  </div>
                )}
              </Card>
            </li>
          ))}
        </ul>
      </ListState>

      {editing && <CategoryDialog category={editing === 'new' ? null : editing} all={rows} onClose={() => setEditing(null)} onDone={done} />}
      {action?.kind === 'toggle' && <ToggleDialog category={action.category} onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'delete' && <DeleteDialog category={action.category} onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'order' && <OrderDialog ids={order} onClose={() => setAction(null)} onDone={done} />}
    </div>
  );
}

function ToggleDialog({ category, onDone, onClose }: { category: Category; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const update = useUpdateCategory();
  const show = !category.is_active;
  return (
    <ReasonDialog
      title={show ? t('marketing_category_showTitle') : t('marketing_category_hideTitle')}
      text={show ? t('marketing_category_showText', { name: category.name }) : t('marketing_category_hideText', { name: category.name })}
      submitLabel={show ? t('marketing_show') : t('marketing_hide')}
      busy={update.isPending}
      error={update.error ? errorText(update.error, t) : null}
      reasonError={fieldError(update.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => update.mutate({ id: category.id, is_active: show, reason }, { onSuccess: () => onDone(show ? t('marketing_category_shown') : t('marketing_category_hiddenDone')) })}
    />
  );
}

function DeleteDialog({ category, onDone, onClose }: { category: Category; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const remove = useDeleteCategory();
  return (
    <ReasonDialog
      title={t('marketing_category_deleteTitle')}
      text={t('marketing_category_deleteText', { name: category.name })}
      submitLabel={t('marketing_delete')}
      tone="danger"
      busy={remove.isPending}
      error={remove.error ? errorText(remove.error, t) : null}
      reasonError={fieldError(remove.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => remove.mutate({ id: category.id, reason }, { onSuccess: () => onDone(t('marketing_category_deleted')) })}
    />
  );
}

function OrderDialog({ ids, onDone, onClose }: { ids: number[]; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const save = useOrderCategories();
  return (
    <ReasonDialog
      title={t('marketing_category_orderTitle')}
      text={t('marketing_category_orderText')}
      submitLabel={t('save')}
      busy={save.isPending}
      error={save.error ? errorText(save.error, t) : null}
      reasonError={fieldError(save.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => save.mutate({ ids, reason }, { onSuccess: () => onDone(t('marketing_order_saved')) })}
    />
  );
}
