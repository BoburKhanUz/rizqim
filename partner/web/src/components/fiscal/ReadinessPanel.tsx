'use client';

import { useMemo, useState } from 'react';
import { CheckCircle2, Save, Search, TriangleAlert } from 'lucide-react';
import { Button, Card, EmptyState, Field, fieldClass, Notice, Pill } from '@rizqim/ui';
import {
  isMxik,
  isPackageCode,
  useFiscalReadiness,
  useOwnerProducts,
  useSaveProductFiscal,
  type FiscalReadiness,
  type Product,
  type ProductKind,
  type ProductMissingMxik,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';
import { DataTable, Td, Th } from '../reports/ui';
import { labelFor } from '../reports/format';
import { sortServerErrors } from '../pos/serverErrors';

interface RowDraft {
  kind: ProductKind;
  mxik: string;
  packageCode: string;
}

const rowFields = ['mxik_code', 'package_code', 'kind'] as const;

// "Fiskal tayyorlik": the products and charges that cannot be sold in fiscal mode for want of an MXIK. The codes are typed
// into the table and saved per row or all at once; a row the server refuses keeps its draft and shows the server's reason.
export function ReadinessPanel({ canEditMenu, canOpenSettings, onOpenSettings }: { canEditMenu: boolean; canOpenSettings: boolean; onOpenSettings: () => void }) {
  const readiness = useFiscalReadiness();
  const products = useOwnerProducts({ enabled: canEditMenu });

  return (
    <Gate query={readiness} count={3}>
      {(data) => <ReadinessBody data={data} products={products.data} productsFailed={products.isError} canEditMenu={canEditMenu} canOpenSettings={canOpenSettings} onOpenSettings={onOpenSettings} />}
    </Gate>
  );
}

function ReadinessBody({
  data,
  products,
  productsFailed,
  canEditMenu,
  canOpenSettings,
  onOpenSettings,
}: {
  data: FiscalReadiness;
  products: Product[] | undefined;
  productsFailed: boolean;
  canEditMenu: boolean;
  canOpenSettings: boolean;
  onOpenSettings: () => void;
}) {
  const { t } = useI18n();
  const save = useSaveProductFiscal();
  const [search, setSearch] = useState('');
  const [drafts, setDrafts] = useState<Record<number, RowDraft>>({});
  const [errors, setErrors] = useState<Record<number, string>>({});
  const [busy, setBusy] = useState<number | 'all' | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [failedNote, setFailedNote] = useState<string | null>(null);

  const byId = useMemo(() => new Map((products ?? []).map((product) => [product.id, product])), [products]);
  const initialOf = (row: ProductMissingMxik): RowDraft => {
    const product = byId.get(row.id);
    return { kind: row.kind === 'service' ? 'service' : 'dish', mxik: product?.mxik_code ?? '', packageCode: product?.package_code ?? '' };
  };
  const draftOf = (row: ProductMissingMxik): RowDraft => drafts[row.id] ?? initialOf(row);

  const isDirty = (row: ProductMissingMxik) => {
    const draft = draftOf(row);
    const initial = initialOf(row);
    return draft.kind !== initial.kind || draft.mxik.trim() !== initial.mxik.trim() || draft.packageCode.trim() !== initial.packageCode.trim();
  };
  // Saving a row needs a whole MXIK; a package code is optional but a typed one must be digits
  const isReady = (row: ProductMissingMxik) => {
    const draft = draftOf(row);
    return isMxik(draft.mxik.trim()) && (draft.packageCode.trim() === '' || isPackageCode(draft.packageCode.trim()));
  };
  const rowProblem = (row: ProductMissingMxik): string | undefined => {
    if (errors[row.id]) return errors[row.id];
    const draft = draftOf(row);
    if (!drafts[row.id]) return undefined;
    if (draft.mxik.trim() !== '' && !isMxik(draft.mxik.trim())) return t('fiscal_err_mxik');
    if (draft.packageCode.trim() !== '' && !isPackageCode(draft.packageCode.trim())) return t('fiscal_err_package');
    return undefined;
  };

  const change = (row: ProductMissingMxik, changes: Partial<RowDraft>) => {
    setDrafts((current) => ({ ...current, [row.id]: { ...draftOf(row), ...changes } }));
    setErrors((current) => Object.fromEntries(Object.entries(current).filter(([id]) => Number(id) !== row.id)));
  };

  const saveOne = async (row: ProductMissingMxik): Promise<boolean> => {
    const draft = draftOf(row);
    const initial = initialOf(row);
    try {
      // PATCH /owner/products/{id}/fiscal: only these fields change, the rest of the product is not sent
      await save.mutateAsync({
        product: { id: row.id },
        fiscal: {
          mxik_code: draft.mxik.trim(),
          ...(draft.packageCode.trim() !== initial.packageCode.trim() ? { package_code: draft.packageCode.trim() || null } : {}),
          ...(draft.kind !== initial.kind ? { kind: draft.kind } : {}),
        },
      });
      setDrafts((current) => Object.fromEntries(Object.entries(current).filter(([id]) => Number(id) !== row.id)));
      return true;
    } catch (error) {
      const sorted = sortServerErrors(error, rowFields, t);
      setErrors((current) => ({ ...current, [row.id]: sorted.form ?? Object.values(sorted.fields)[0] ?? errorText(error, t) }));
      return false;
    }
  };

  const saveRow = async (row: ProductMissingMxik) => {
    setNotice(null);
    setFailedNote(null);
    setBusy(row.id);
    const done = await saveOne(row);
    setBusy(null);
    if (done) setNotice(t('fiscal_ready_saved', { n: 1 }));
  };

  const dirtyReady = data.products_missing_mxik.filter((row) => isDirty(row) && isReady(row));
  const saveAll = async () => {
    setNotice(null);
    setFailedNote(null);
    setBusy('all');
    let saved = 0;
    let failed = 0;
    // One after another: a failing one must not stop the rest
    for (const row of dirtyReady) {
      if (await saveOne(row)) saved += 1;
      else failed += 1;
    }
    setBusy(null);
    if (saved > 0) setNotice(t('fiscal_ready_saved', { n: saved }));
    if (failed > 0) setFailedNote(t('fiscal_ready_failed', { n: failed }));
  };

  const needle = search.trim().toLowerCase();
  const rows = data.products_missing_mxik.filter((row) => needle === '' || row.name.toLowerCase().includes(needle));
  const stateTone = data.enabled ? 'success' : 'warning';

  return (
    <div className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('fiscal_ready_intro')}</p>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Card className="flex flex-col gap-1">
          <span className="text-footnote font-bold text-fg-muted">{t('fiscal_tab_settings')}</span>
          <Pill tone={stateTone} className="self-start">
            {data.enabled ? t('fiscal_ready_on') : t('fiscal_ready_off')}
          </Pill>
        </Card>
        <Card className="flex flex-col gap-1">
          <span className="text-footnote font-bold text-fg-muted">{t('fiscal_ready_tin')}</span>
          <Pill tone={data.tin_set ? 'success' : 'danger'} className="self-start">
            {data.tin_set ? t('fiscal_ready_tinSet') : t('fiscal_ready_tinMissing')}
          </Pill>
        </Card>
        <Card className="flex flex-col gap-1">
          <span className="text-footnote font-bold text-fg-muted">{t('fiscal_ready_regime')}</span>
          <span className="text-body font-bold text-fg">{data.tax_regime ? labelFor(t, 'fiscal_regime_', data.tax_regime) : t('fiscal_ready_regimeNone')}</span>
        </Card>
        <Card className="flex flex-col gap-1">
          <span className="text-footnote font-bold text-fg-muted">{t('fiscal_ready_productsCount', { n: data.products_missing_mxik.length })}</span>
          <span className="text-footnote text-fg-muted">{t('fiscal_ready_chargesCount', { n: data.charges_missing_mxik.length })}</span>
        </Card>
      </div>

      {!data.enabled && (
        <p role="note" className="flex items-start gap-2 rounded-card bg-warning-soft px-4 py-3 text-footnote text-warning-soft-fg">
          <TriangleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
          {t('fiscal_ready_offNote')}
        </p>
      )}

      {data.charges_missing_mxik.length > 0 && (
        <Card className="flex flex-col gap-2">
          <h2 className="text-headline font-bold text-fg">{t('fiscal_ready_charges')}</h2>
          <p className="text-footnote text-fg-muted">{t('fiscal_ready_chargesHint')}</p>
          <ul className="flex flex-wrap gap-2">
            {data.charges_missing_mxik.map((charge) => (
              <li key={charge}>
                <Pill tone="warning">{labelFor(t, 'fiscal_charge_', charge)}</Pill>
              </li>
            ))}
          </ul>
          {canOpenSettings && (
            <div>
              <Button variant="secondary" onClick={onOpenSettings}>
                {t('fiscal_ready_openSettings')}
              </Button>
            </div>
          )}
        </Card>
      )}

      <Notice message={notice} />
      {failedNote && (
        <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-footnote font-bold text-danger-soft-fg">
          {failedNote}
        </p>
      )}

      {data.products_missing_mxik.length === 0 ? (
        <EmptyState icon={CheckCircle2} title={t('fiscal_ready_allGood')} text={t('fiscal_ready_allGoodText')} />
      ) : (
        <Card className="flex flex-col gap-3">
          {!canEditMenu && <p className="text-footnote text-fg-muted">{t('fiscal_ready_noEdit')}</p>}
          {canEditMenu && productsFailed && <p role="alert" className="text-footnote font-bold text-danger-soft-fg">{t('fiscal_ready_noMenuAccess')}</p>}
          <div className="flex flex-wrap items-end justify-between gap-3">
            <Field label={t('fiscal_ready_search')}>
              {(control) => (
                <div className="relative">
                  <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
                  <input {...control} type="search" value={search} onChange={(event) => setSearch(event.target.value)} className={`${fieldClass} pl-9 sm:w-72`} />
                </div>
              )}
            </Field>
            {canEditMenu && (
              <Button icon={Save} onClick={() => void saveAll()} disabled={dirtyReady.length === 0 || busy !== null} loading={busy === 'all'}>
                {t('fiscal_ready_saveAll', { n: dirtyReady.length })}
              </Button>
            )}
          </div>

          {rows.length === 0 ? (
            <p className="py-4 text-center text-body text-fg-muted">{t('fiscal_ready_noMatch')}</p>
          ) : (
            <DataTable caption={t('fiscal_tab_readiness')}>
              <thead>
                <tr>
                  <Th>{t('fiscal_ready_colName')}</Th>
                  <Th>{t('fiscal_ready_colKind')}</Th>
                  <Th>{t('fiscal_ready_colMxik')}</Th>
                  <Th>{t('fiscal_ready_colPackage')}</Th>
                  <Th>{t('fiscal_ready_colAction')}</Th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => {
                  const draft = draftOf(row);
                  const problem = rowProblem(row);
                  const editable = canEditMenu;
                  return (
                    <tr key={row.id}>
                      <Td className="min-w-40 font-bold">
                        {row.name}
                        {row.status !== 'active' && (
                          <Pill className="ml-2" tone="neutral">
                            {t('fiscal_ready_inactive')}
                          </Pill>
                        )}
                      </Td>
                      <Td>
                        <select
                          aria-label={t('fiscal_ready_kindAria', { name: row.name })}
                          className={`${fieldClass} w-auto`}
                          value={draft.kind}
                          disabled={!editable}
                          onChange={(event) => change(row, { kind: event.target.value as ProductKind })}
                        >
                          <option value="dish">{t('fiscal_kind_dish')}</option>
                          <option value="service">{t('fiscal_kind_service')}</option>
                        </select>
                      </Td>
                      <Td>
                        <input
                          aria-label={t('fiscal_ready_mxikAria', { name: row.name })}
                          aria-invalid={problem ? true : undefined}
                          className={`${fieldClass} w-48 font-mono`}
                          inputMode="numeric"
                          maxLength={17}
                          value={draft.mxik}
                          disabled={!editable}
                          onChange={(event) => change(row, { mxik: event.target.value })}
                        />
                        {problem && (
                          <p role="alert" className="mt-1 max-w-64 text-footnote font-bold text-danger-soft-fg">
                            {problem}
                          </p>
                        )}
                      </Td>
                      <Td>
                        <input
                          aria-label={t('fiscal_ready_packageAria', { name: row.name })}
                          className={`${fieldClass} w-36 font-mono`}
                          inputMode="numeric"
                          maxLength={20}
                          value={draft.packageCode}
                          disabled={!editable}
                          onChange={(event) => change(row, { packageCode: event.target.value })}
                        />
                      </Td>
                      <Td>
                        <Button variant="secondary" aria-label={t('fiscal_ready_saveRowAria', { name: row.name })} onClick={() => void saveRow(row)} disabled={!editable || !isDirty(row) || !isReady(row) || busy !== null} loading={busy === row.id}>
                          {t('fiscal_ready_saveRow')}
                        </Button>
                      </Td>
                    </tr>
                  );
                })}
              </tbody>
            </DataTable>
          )}
        </Card>
      )}
    </div>
  );
}
