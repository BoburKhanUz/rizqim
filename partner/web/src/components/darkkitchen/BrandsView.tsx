'use client';

import { useMemo, useState } from 'react';
import { Pencil, Plus, Store, Tags, Trash2 } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, Dialog, EmptyState, Field, fieldClass, InlineError, Notice, PageHeader, Switch } from '@rizqim/ui';
import { useMenuBranches, useOwnerProducts, type Product } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from '../menu/access';
import { describeError } from '../menu/serverError';
import { DataTable, Td, Th } from '../reports/ui';
import { CHANNELS, type Brand, type Channel } from './api';
import { BrandBadge, channelLabel } from './BrandBadge';
import { useAssignBrand, useBrands, useDeleteBrand, useSaveBrand, useSaveBrandBranches, useSaveBrandChannels, useToggleBrand } from './hooks';

const brandIdOf = (product: Product): number | null => (product as Product & { brand_id?: number | null }).brand_id ?? null;

// /brands "Brendlar" (P6-55, module multi_brand): several brands sold from one kitchen. Each brand has a short code and a
// colour (the kitchen ticket and the label show them), its channels (hidden on some, a price percent on others) and its
// branches; the dishes are put under a brand below. The server applies every rule (catalogue, till, checkout).
export function BrandsView() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const canEdit = access.can('menu.manage');
  const brands = useBrands();
  const [editing, setEditing] = useState<Brand | 'new' | null>(null);
  const [channels, setChannels] = useState<Brand | null>(null);
  const [branches, setBranches] = useState<Brand | null>(null);
  const [deleting, setDeleting] = useState<Brand | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const toggle = useToggleBrand();
  const remove = useDeleteBrand(() => {
    setDeleting(null);
    setNotice(t('dk_brandDeleted'));
  });

  return (
    <>
      <PageHeader title={t('dk_brandsTitle')} subtitle={t('dk_brandsSubtitle')} />
      <Notice message={notice} />
      {brands.isPending ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : brands.isError ? (
        <InlineError title={t('loadErrorTitle')} message={describeError(brands.error, t).message} retryLabel={t('retry')} retrying={brands.isFetching} onRetry={() => void brands.refetch()} />
      ) : (
        <div className="flex flex-col gap-4">
          {canEdit && (
            <div>
              <Button icon={Plus} onClick={() => setEditing('new')}>
                {t('dk_brandAdd')}
              </Button>
            </div>
          )}
          {toggle.error && (
            <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
              {describeError(toggle.error, t).message}
            </p>
          )}
          {brands.data.brands.length === 0 ? (
            <EmptyState icon={Tags} title={t('dk_brandsEmpty')} text={t('dk_brandsEmptyText')} />
          ) : (
            <ul className="grid gap-3 lg:grid-cols-2">
              {brands.data.brands.map((brand) => (
                <li key={brand.id}>
                  <Card className="flex flex-col gap-3">
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex flex-col gap-1">
                        <h2 className="flex items-center gap-2 text-headline font-extrabold text-fg">
                          <BrandBadge code={brand.short_code} color={brand.color} />
                          {brand.name}
                        </h2>
                        <p className="text-footnote text-fg-muted">{t('dk_brandProducts', { n: brand.products_count })}</p>
                      </div>
                      <Switch
                        checked={brand.is_active}
                        label={t('dk_brandActive', { name: brand.name })}
                        disabled={!canEdit || toggle.isPending}
                        onChange={(isActive) => toggle.mutate({ id: brand.id, isActive })}
                      />
                    </div>
                    <p className="text-footnote text-fg">{summary(brand, t)}</p>
                    {canEdit && (
                      <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" icon={Pencil} onClick={() => setEditing(brand)}>
                          {t('dk_edit')}
                        </Button>
                        <Button variant="secondary" onClick={() => setChannels(brand)}>
                          {t('dk_brandChannels')}
                        </Button>
                        <Button variant="secondary" icon={Store} onClick={() => setBranches(brand)}>
                          {t('dk_brandBranches')}
                        </Button>
                        <Button variant="ghost" icon={Trash2} aria-label={t('dk_brandDeleteNamed', { name: brand.name })} onClick={() => setDeleting(brand)}>
                          {t('dk_delete')}
                        </Button>
                      </div>
                    )}
                  </Card>
                </li>
              ))}
            </ul>
          )}
          {brands.data.brands.length > 0 && <ProductBrands brands={brands.data.brands} canEdit={canEdit} onDone={setNotice} />}
        </div>
      )}

      {editing !== null && (
        <BrandDialog
          brand={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            setNotice(t('dk_brandSaved'));
          }}
        />
      )}
      {channels && (
        <ChannelsDialog
          brand={channels}
          onClose={() => setChannels(null)}
          onSaved={() => {
            setChannels(null);
            setNotice(t('dk_channelsSaved'));
          }}
        />
      )}
      {branches && (
        <BranchesDialog
          brand={branches}
          onClose={() => setBranches(null)}
          onSaved={() => {
            setBranches(null);
            setNotice(t('dk_branchesSaved'));
          }}
        />
      )}
      {deleting && (
        <ConfirmDialog
          title={t('dk_brandDeleteTitle')}
          text={t('dk_brandDeleteText', { name: deleting.name })}
          confirmLabel={t('dk_delete')}
          cancelLabel={t('dk_cancel')}
          closeLabel={t('dk_close')}
          busy={remove.isPending}
          error={remove.error ? describeError(remove.error, t).message : null}
          onConfirm={() => remove.mutate(deleting.id)}
          onClose={() => setDeleting(null)}
        />
      )}
    </>
  );
}

function summary(brand: Brand, t: ReturnType<typeof useI18n>['t']): string {
  const hidden = brand.channels.filter((rule) => rule.is_hidden).map((rule) => channelLabel(rule.channel, t));
  const priced = brand.channels.filter((rule) => !rule.is_hidden && rule.price_percent !== null).map((rule) => `${channelLabel(rule.channel, t)} ${Number(rule.price_percent) > 0 ? '+' : ''}${Number(rule.price_percent)}%`);
  const parts = [hidden.length > 0 ? t('dk_hiddenOn', { list: hidden.join(', ') }) : t('dk_allChannels'), ...(priced.length > 0 ? [t('dk_pricedOn', { list: priced.join(', ') })] : [])];
  if (brand.off_branch_ids.length > 0) parts.push(t('dk_offBranches', { n: brand.off_branch_ids.length }));
  return parts.join(' · ');
}

function BrandDialog({ brand, onClose, onSaved }: { brand: Brand | null; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(brand?.name ?? '');
  const [code, setCode] = useState(brand?.short_code ?? '');
  const [color, setColor] = useState(brand?.color ?? '#E11D48');
  const [logo, setLogo] = useState(brand?.logo_url ?? '');
  const save = useSaveBrand(onSaved);
  const valid = name.trim() !== '' && /^[\p{L}\p{N}]{1,8}$/u.test(code.trim()) && /^#[0-9A-Fa-f]{6}$/.test(color);

  return (
    <Dialog
      open
      onClose={onClose}
      busy={save.isPending}
      closeLabel={t('dk_close')}
      title={brand ? t('dk_brandEdit') : t('dk_brandAdd')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('dk_cancel')}
          </Button>
          <Button
            loading={save.isPending}
            disabled={!valid}
            onClick={() => save.mutate({ id: brand?.id ?? null, input: { name: name.trim(), short_code: code.trim(), color, logo_url: logo.trim() || null, is_active: brand?.is_active ?? true } })}
          >
            {t('dk_save')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <Field label={t('dk_brandName')}>{(control) => <input {...control} className={fieldClass} value={name} maxLength={120} onChange={(event) => setName(event.target.value)} />}</Field>
        <Field label={t('dk_brandCode')} hint={t('dk_brandCodeHint')}>
          {(control) => <input {...control} className={fieldClass} value={code} maxLength={8} onChange={(event) => setCode(event.target.value.toUpperCase())} />}
        </Field>
        <Field label={t('dk_brandColor')}>
          {(control) => (
            <div className="flex items-center gap-3">
              <input {...control} type="color" className="h-11 w-16 rounded-control border border-line" value={color} onChange={(event) => setColor(event.target.value.toUpperCase())} />
              <BrandBadge code={code || '—'} color={color} />
            </div>
          )}
        </Field>
        <Field label={t('dk_brandLogo')} hint={t('dk_brandLogoHint')}>
          {(control) => <input {...control} type="url" className={fieldClass} value={logo} placeholder="https://" onChange={(event) => setLogo(event.target.value)} />}
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

function ChannelsDialog({ brand, onClose, onSaved }: { brand: Brand; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const initial = useMemo(() => new Map(brand.channels.map((rule) => [rule.channel, rule])), [brand.channels]);
  const [rows, setRows] = useState(() =>
    CHANNELS.map((channel) => ({ channel, sold: !(initial.get(channel)?.is_hidden ?? false), percent: initial.get(channel)?.price_percent ? String(Number(initial.get(channel)?.price_percent)) : '' })),
  );
  const save = useSaveBrandChannels(onSaved);
  const invalid = rows.some((row) => row.percent.trim() !== '' && (Number.isNaN(Number(row.percent.replace(',', '.'))) || Number(row.percent.replace(',', '.')) < -90 || Number(row.percent.replace(',', '.')) > 300));
  const update = (channel: Channel, patch: Partial<{ sold: boolean; percent: string }>) => setRows((list) => list.map((row) => (row.channel === channel ? { ...row, ...patch } : row)));

  return (
    <Dialog
      open
      onClose={onClose}
      busy={save.isPending}
      closeLabel={t('dk_close')}
      title={t('dk_channelsTitle', { name: brand.name })}
      description={t('dk_channelsHint')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('dk_cancel')}
          </Button>
          <Button
            loading={save.isPending}
            disabled={invalid}
            onClick={() =>
              save.mutate({
                id: brand.id,
                channels: rows.map((row) => ({ channel: row.channel, is_hidden: !row.sold, price_percent: row.sold && row.percent.trim() !== '' ? row.percent.replace(',', '.').trim() : null })),
              })
            }
          >
            {t('dk_save')}
          </Button>
        </>
      }
    >
      <DataTable caption={t('dk_channelsTitle', { name: brand.name })}>
        <thead>
          <tr>
            <Th>{t('dk_channel')}</Th>
            <Th>{t('dk_sold')}</Th>
            <Th>{t('dk_percent')}</Th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.channel}>
              <Td>{channelLabel(row.channel, t)}</Td>
              <Td>
                <Switch checked={row.sold} label={t('dk_soldOn', { channel: channelLabel(row.channel, t) })} onChange={(sold) => update(row.channel, { sold })} />
              </Td>
              <Td>
                <input
                  aria-label={t('dk_percentOn', { channel: channelLabel(row.channel, t) })}
                  className={`${fieldClass} w-28`}
                  inputMode="decimal"
                  value={row.percent}
                  disabled={!row.sold}
                  placeholder="0"
                  onChange={(event) => update(row.channel, { percent: event.target.value })}
                />
              </Td>
            </tr>
          ))}
        </tbody>
      </DataTable>
      {invalid && (
        <p role="alert" className="mt-2 text-footnote font-bold text-danger-soft-fg">
          {t('dk_percentInvalid')}
        </p>
      )}
      {save.error && (
        <p role="alert" className="mt-2 text-footnote font-bold text-danger-soft-fg">
          {describeError(save.error, t).message}
        </p>
      )}
    </Dialog>
  );
}

function BranchesDialog({ brand, onClose, onSaved }: { brand: Brand; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const branches = useMenuBranches();
  const [off, setOff] = useState(() => new Set(brand.off_branch_ids));
  const save = useSaveBrandBranches(onSaved);

  return (
    <Dialog
      open
      onClose={onClose}
      busy={save.isPending}
      closeLabel={t('dk_close')}
      title={t('dk_branchesTitle', { name: brand.name })}
      description={t('dk_branchesHint')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('dk_cancel')}
          </Button>
          <Button loading={save.isPending} disabled={!branches.data?.length} onClick={() => save.mutate({ id: brand.id, branches: (branches.data ?? []).map((branch) => ({ branch_id: branch.id, is_active: !off.has(branch.id) })) })}>
            {t('dk_save')}
          </Button>
        </>
      }
    >
      <ul className="flex flex-col gap-2">
        {(branches.data ?? []).map((branch) => (
          <li key={branch.id} className="flex items-center justify-between gap-3">
            <span className="text-body text-fg">{branch.name}</span>
            <Switch
              checked={!off.has(branch.id)}
              label={t('dk_onBranch', { branch: branch.name })}
              onChange={(on) =>
                setOff((current) => {
                  const next = new Set(current);
                  if (on) next.delete(branch.id);
                  else next.add(branch.id);
                  return next;
                })
              }
            />
          </li>
        ))}
      </ul>
      {save.error && (
        <p role="alert" className="mt-2 text-footnote font-bold text-danger-soft-fg">
          {describeError(save.error, t).message}
        </p>
      )}
    </Dialog>
  );
}

// The dishes and their brand: tick some, choose a brand, move them
function ProductBrands({ brands, canEdit, onDone }: { brands: Brand[]; canEdit: boolean; onDone: (message: string) => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const [picked, setPicked] = useState<Set<number>>(new Set());
  const [target, setTarget] = useState<string>(String(brands[0]?.id ?? ''));
  const [search, setSearch] = useState('');
  const byId = useMemo(() => new Map(brands.map((brand) => [brand.id, brand])), [brands]);
  const assign = useAssignBrand((changed) => {
    setPicked(new Set());
    onDone(t('dk_assigned', { n: changed }));
  });
  const q = search.trim().toLowerCase();
  const list = (products.data ?? []).filter((product) => q === '' || product.name.toLowerCase().includes(q));

  return (
    <Card className="flex flex-col gap-3">
      <h2 className="text-headline font-extrabold text-fg">{t('dk_productsTitle')}</h2>
      <p className="text-footnote text-fg-muted">{t('dk_productsHint')}</p>
      <div className="flex flex-wrap items-end gap-3">
        <Field label={t('dk_search')} className="min-w-56">
          {(control) => <input {...control} className={fieldClass} value={search} onChange={(event) => setSearch(event.target.value)} />}
        </Field>
        {canEdit && (
          <>
            <Field label={t('dk_moveTo')} className="min-w-56">
              {(control) => (
                <select {...control} className={fieldClass} value={target} onChange={(event) => setTarget(event.target.value)}>
                  {brands.map((brand) => (
                    <option key={brand.id} value={brand.id}>
                      {brand.name}
                    </option>
                  ))}
                  <option value="house">{t('dk_houseBrand')}</option>
                </select>
              )}
            </Field>
            <Button loading={assign.isPending} disabled={picked.size === 0} onClick={() => assign.mutate({ brandId: target === 'house' ? null : Number(target), productIds: [...picked] })}>
              {t('dk_move', { n: picked.size })}
            </Button>
          </>
        )}
      </div>
      {assign.error && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {describeError(assign.error, t).message}
        </p>
      )}
      {products.isPending ? (
        <CardSkeletons count={1} lines={4} label={t('loading')} />
      ) : (
        <DataTable caption={t('dk_productsTitle')}>
          <thead>
            <tr>
              {canEdit && <Th>{t('dk_pick')}</Th>}
              <Th>{t('dk_dish')}</Th>
              <Th>{t('dk_brand')}</Th>
            </tr>
          </thead>
          <tbody>
            {list.map((product) => {
              const brand = byId.get(brandIdOf(product) ?? -1);
              return (
                <tr key={product.id}>
                  {canEdit && (
                    <Td>
                      <input
                        type="checkbox"
                        className="size-5"
                        aria-label={t('dk_pickNamed', { name: product.name })}
                        checked={picked.has(product.id)}
                        onChange={(event) =>
                          setPicked((current) => {
                            const next = new Set(current);
                            if (event.target.checked) next.add(product.id);
                            else next.delete(product.id);
                            return next;
                          })
                        }
                      />
                    </Td>
                  )}
                  <Td>{product.name}</Td>
                  <Td>{brand ? <BrandBadge code={brand.short_code} color={brand.color} label={brand.name} /> : <span className="text-fg-muted">{t('dk_houseBrand')}</span>}</Td>
                </tr>
              );
            })}
          </tbody>
        </DataTable>
      )}
    </Card>
  );
}
