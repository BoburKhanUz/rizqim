'use client';

import { useState } from 'react';
import { Button, Card, Field, fieldClass, Switch } from '@rizqim/ui';
import { unwrap, usePosFormat, useStaffApi, type Product, type StaffApi } from '@rizqim/staff';
import { useQueryClient } from '@tanstack/react-query';
import { useI18n } from '../I18nProvider';
import { useBranchChoice } from './BranchChoice';
import { ErrorBanner, SuccessNote } from './ErrorBanner';
import { describeError } from './serverError';

// P6-25 (docs/API.md "Kurslar, og'irlik, stolga olib kelish"): how a dish is sold in a kabobxona or baliqxona — its ×N
// buttons and grill time (module skewer_buttons) and "weight later" (module weight_later). Shown only when the branch's
// format has one of the modules; PATCH /owner/products/{id}/pos-service sends only what changed. The endpoint is called
// through the generated client's untyped door (the spec is regenerated once for the wave).

export const MULTIPLIERS = [2, 3, 4, 5, 6, 7, 8, 9, 10] as const;
export type WeightMode = 'raw' | 'cooked';

export interface PosServiceFields {
  quick_multipliers: number[] | null;
  wait_minutes: number | null;
  weight_mode: WeightMode | null;
}

interface Door {
  PATCH(path: string, init?: { params?: { path?: Record<string, string | number> }; body?: unknown }): Promise<{ data?: unknown; error?: unknown; response: Response }>;
}

export async function saveProductPosService(api: StaffApi, productId: number, patch: Partial<PosServiceFields>): Promise<PosServiceFields> {
  const door = api.client as unknown as Door;
  const body = unwrap(await door.PATCH('/v1/owner/products/{id}/pos-service', { params: { path: { id: productId } }, body: patch })) as { data?: PosServiceFields } | undefined;
  return body?.data as PosServiceFields;
}

// The fields as the product has them (the owner's product list carries every column)
export function posServiceOf(product: Product): PosServiceFields {
  const raw = product as unknown as Partial<PosServiceFields>;
  return {
    quick_multipliers: Array.isArray(raw.quick_multipliers) ? raw.quick_multipliers.map(Number) : null,
    wait_minutes: typeof raw.wait_minutes === 'number' ? raw.wait_minutes : null,
    weight_mode: raw.weight_mode === 'raw' || raw.weight_mode === 'cooked' ? raw.weight_mode : null,
  };
}

// The modules of the branch the panel shows: which parts of the form apply
export function useServiceModules(): { skewer: boolean; weight: boolean } {
  const choice = useBranchChoice();
  const format = usePosFormat(choice.branchId, { retry: false });
  const modules: string[] = (format.data as { modules?: string[] } | undefined)?.modules ?? [];
  return { skewer: modules.includes('skewer_buttons'), weight: modules.includes('weight_later') };
}

export function ProductPosService({ product, canManage }: { product: Product; canManage: boolean }) {
  const modules = useServiceModules();
  if (!modules.skewer && !modules.weight) return null;
  return <ProductPosServiceForm product={product} canManage={canManage} skewer={modules.skewer} weight={modules.weight} />;
}

export function ProductPosServiceForm({ product, canManage, skewer, weight }: { product: Product; canManage: boolean; skewer: boolean; weight: boolean }) {
  const { t } = useI18n();
  const api = useStaffApi();
  const queries = useQueryClient();
  const initial = posServiceOf(product);
  const [multipliers, setMultipliers] = useState<number[]>(initial.quick_multipliers ?? []);
  const [wait, setWait] = useState(initial.wait_minutes === null ? '' : String(initial.wait_minutes));
  const [mode, setMode] = useState<WeightMode | ''>(initial.weight_mode ?? '');
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [banner, setBanner] = useState<{ message: string; details: string[] } | null>(null);
  const [waitError, setWaitError] = useState<string | null>(null);

  const toggle = (value: number, on: boolean) => {
    setSaved(false);
    setMultipliers((current) => (on ? [...current, value].sort((a, b) => a - b) : current.filter((item) => item !== value)));
  };

  const submit = async () => {
    setSaved(false);
    setBanner(null);
    const patch: Partial<PosServiceFields> = {};
    if (skewer) {
      const minutes = wait.trim() === '' ? null : Number(wait.trim());
      if (minutes !== null && (!Number.isInteger(minutes) || minutes < 0 || minutes > 240)) {
        setWaitError(t('menu_service_wait_invalid'));
        return;
      }
      setWaitError(null);
      patch.quick_multipliers = multipliers.length > 0 ? multipliers : null;
      patch.wait_minutes = minutes;
    }
    if (weight) patch.weight_mode = mode === '' ? null : mode;

    setSaving(true);
    try {
      await saveProductPosService(api, product.id, patch);
      setSaved(true);
      // The product list carries the fields: read again so the form opens with what the server stored
      void queries.invalidateQueries();
    } catch (error) {
      const view = describeError(error, t);
      setBanner({ message: view.message, details: Object.values(view.fields) });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex flex-col gap-3">
      <p className="text-footnote text-fg-muted">{t('menu_service_hint')}</p>
      <fieldset disabled={!canManage || saving} className="flex min-w-0 flex-col gap-3 border-0 p-0">
        {skewer && (
          <Card role="group" aria-label={t('menu_service_skewer')} className="flex flex-col gap-3">
            <h3 className="text-body font-bold text-fg">{t('menu_service_skewer')}</h3>
            <p className="text-footnote text-fg-muted">{t('menu_service_multipliers_hint')}</p>
            <ul className="flex flex-wrap gap-3">
              {MULTIPLIERS.map((value) => (
                <li key={value} className="flex items-center gap-1">
                  <Switch checked={multipliers.includes(value)} onChange={(on) => toggle(value, on)} label={t('menu_service_multiplier', { n: String(value) })} />
                  <span className="text-body text-fg">×{value}</span>
                </li>
              ))}
            </ul>
            <Field label={t('menu_service_wait')} hint={t('menu_service_wait_hint')} error={waitError ?? undefined}>
              {(props) => (
                <input
                  {...props}
                  className={`${fieldClass} max-w-44`}
                  inputMode="numeric"
                  value={wait}
                  onChange={(event) => {
                    setSaved(false);
                    setWait(event.target.value);
                  }}
                />
              )}
            </Field>
          </Card>
        )}
        {weight && (
          <Card role="group" aria-label={t('menu_service_weight')} className="flex flex-col gap-3">
            <h3 className="text-body font-bold text-fg">{t('menu_service_weight')}</h3>
            <Field label={t('menu_service_weight_mode')} hint={t('menu_service_weight_hint')}>
              {(props) => (
                <select
                  {...props}
                  className={`${fieldClass} max-w-xs`}
                  value={mode}
                  onChange={(event) => {
                    setSaved(false);
                    setMode(event.target.value as WeightMode | '');
                  }}
                >
                  <option value="">{t('menu_service_weight_none')}</option>
                  <option value="raw">{t('menu_service_weight_raw')}</option>
                  <option value="cooked">{t('menu_service_weight_cooked')}</option>
                </select>
              )}
            </Field>
          </Card>
        )}
      </fieldset>
      {banner && <ErrorBanner message={banner.message} details={banner.details} />}
      {saved && <SuccessNote>{t('menu_saved')}</SuccessNote>}
      {canManage && (
        <div>
          <Button onClick={() => void submit()} loading={saving}>
            {t('menu_service_save')}
          </Button>
        </div>
      )}
    </div>
  );
}
