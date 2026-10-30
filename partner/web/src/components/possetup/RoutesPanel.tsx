'use client';

import { useMemo, useState } from 'react';
import { Route } from 'lucide-react';
import { Button, Card, EmptyState, FormError, Notice, Pill } from '@rizqim/ui';
import { useAssignStationRoutes, useKitchenStations, useOwnerCategories, useOwnerProducts, useStationRoutes } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';
import { SelectField, TextField } from '../pos/fields';

const NONE = '';

// Which station cooks what: tick categories and dishes, choose a station and assign them in one go. A dish's own route wins
// over its category's; a dish or category on another station moves. "No station" takes the ticked ones off any station.
export function RoutesPanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const stations = useKitchenStations(branchId);
  const routes = useStationRoutes(branchId);
  const categories = useOwnerCategories();
  const products = useOwnerProducts();
  const assign = useAssignStationRoutes();
  const [categoryIds, setCategoryIds] = useState<number[]>([]);
  const [productIds, setProductIds] = useState<number[]>([]);
  const [target, setTarget] = useState<string>(NONE);
  const [search, setSearch] = useState('');
  const [notice, setNotice] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);

  const stationName = useMemo(() => new Map((stations.data?.stations ?? []).map((station) => [station.id, station.name])), [stations.data]);
  const routeOfCategory = useMemo(() => new Map((routes.data ?? []).filter((route) => route.category_id !== null).map((route) => [route.category_id as number, route.station_id])), [routes.data]);
  const routeOfProduct = useMemo(() => new Map((routes.data ?? []).filter((route) => route.product_id !== null).map((route) => [route.product_id as number, route.station_id])), [routes.data]);

  const needle = search.trim().toLowerCase();
  const shownProducts = (products.data ?? []).filter((product) => needle === '' || product.name.toLowerCase().includes(needle));
  const shownCategories = (categories.data ?? []).filter((category) => needle === '' || category.name.toLowerCase().includes(needle));
  const selected = categoryIds.length + productIds.length;

  const toggle = (list: number[], id: number, set: (next: number[]) => void) => {
    set(list.includes(id) ? list.filter((item) => item !== id) : [...list, id]);
    setNotice(null);
  };

  const submit = (stationId: number | null) => {
    setFailure(null);
    setNotice(null);
    assign.mutate(
      { branch_id: branchId, station_id: stationId, category_ids: categoryIds, product_ids: productIds },
      {
        onSuccess: () => {
          setNotice(stationId === null ? t('pos_routes_removed', { n: selected }) : t('pos_routes_assigned', { n: selected, station: stationName.get(stationId) ?? '' }));
          setCategoryIds([]);
          setProductIds([]);
        },
        onError: (error) => setFailure(errorText(error, t)),
      },
    );
  };

  const badge = (id: number | undefined) => {
    if (id === undefined) return <Pill tone="neutral">{t('pos_routes_noStation')}</Pill>;
    return <Pill tone="info">{stationName.get(id) ?? `#${id}`}</Pill>;
  };

  return (
    <Gate query={stations} count={2}>
      {(stationData) => (
        <Gate query={routes} count={2}>
          {() => {
            // P6-33: the expo (assembly) station cooks nothing of its own: no dishes go to it
            const active = stationData.stations.filter((station) => station.is_active && station.kind !== 'expo');
            if (stationData.stations.length === 0) return <EmptyState icon={Route} title={t('pos_routes_noStations')} text={t('pos_routes_noStationsText')} />;
            return (
              <Card className="flex flex-col gap-4">
                <div>
                  <h2 className="text-headline font-bold text-fg">{t('pos_routes_title')}</h2>
                  <p className="text-footnote text-fg-muted">{t('pos_routes_intro')}</p>
                </div>
                <Notice message={notice} />
                <FormError message={failure} />

                <div className="flex flex-wrap items-end gap-3">
                  <SelectField
                    label={t('pos_routes_station')}
                    value={target}
                    onChange={setTarget}
                    className="sm:min-w-64"
                    options={[{ value: NONE, label: t('pos_routes_chooseStation') }, ...active.map((station) => ({ value: String(station.id), label: station.name }))]}
                  />
                  <Button onClick={() => submit(Number(target))} disabled={target === NONE || selected === 0} loading={assign.isPending}>
                    {t('pos_routes_assign', { n: selected })}
                  </Button>
                  <Button variant="secondary" onClick={() => submit(null)} disabled={selected === 0 || assign.isPending}>
                    {t('pos_routes_remove')}
                  </Button>
                  <TextField label={t('pos_routes_search')} value={search} onChange={setSearch} className="sm:min-w-56" />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                  <fieldset className="flex flex-col gap-1">
                    <legend className="mb-1 text-body font-bold text-fg">{t('pos_routes_categories')}</legend>
                    {shownCategories.length === 0 && <p className="text-footnote text-fg-muted">{t('pos_routes_none')}</p>}
                    {shownCategories.map((category) => (
                      <label key={category.id} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-2 hover:bg-surface-2">
                        <input type="checkbox" checked={categoryIds.includes(category.id)} onChange={() => toggle(categoryIds, category.id, setCategoryIds)} className="size-5 shrink-0 accent-[var(--rz-brand)]" />
                        <span className="min-w-0 flex-1 truncate text-body text-fg">{category.name}</span>
                        {badge(routeOfCategory.get(category.id))}
                      </label>
                    ))}
                  </fieldset>
                  <fieldset className="flex max-h-[28rem] flex-col gap-1 overflow-y-auto">
                    <legend className="mb-1 text-body font-bold text-fg">{t('pos_routes_products')}</legend>
                    {shownProducts.length === 0 && <p className="text-footnote text-fg-muted">{t('pos_routes_none')}</p>}
                    {shownProducts.map((product) => {
                      const own = routeOfProduct.get(product.id);
                      const viaCategory = own === undefined ? routeOfCategory.get(product.category_id) : undefined;
                      return (
                        <label key={product.id} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-2 hover:bg-surface-2">
                          <input type="checkbox" checked={productIds.includes(product.id)} onChange={() => toggle(productIds, product.id, setProductIds)} className="size-5 shrink-0 accent-[var(--rz-brand)]" />
                          <span className="min-w-0 flex-1 truncate text-body text-fg">{product.name}</span>
                          {own !== undefined ? badge(own) : viaCategory !== undefined ? <Pill tone="neutral">{t('pos_routes_viaCategory', { station: stationName.get(viaCategory) ?? `#${viaCategory}` })}</Pill> : badge(undefined)}
                        </label>
                      );
                    })}
                  </fieldset>
                </div>
              </Card>
            );
          }}
        </Gate>
      )}
    </Gate>
  );
}
