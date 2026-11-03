'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { Pencil, Plus, ReceiptText, Sandwich, Trash2 } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, Dialog, EmptyState, Field, fieldClass, formatMoney, InlineError, Notice, PageHeader, Pill, Switch } from '@rizqim/ui';
import { useModifierGroups, useOwnerProducts, type Product } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from '../menu/access';
import { ErrorBanner } from '../menu/ErrorBanner';
import { parseDecimal } from '../menu/numbers';
import { describeError } from '../menu/serverError';
import type { Combo, ComboFiscalMode, ComboOption, ComboPick, ComboPreviewLine, ComboSlot, ComboSlotInput } from './api';
import { useComboPreview, useCombos, useDeleteCombo, useSaveCombo, useSaveComboFiscalMode } from './hooks';

const MAX_SLOTS = 8;
const MAX_OPTIONS = 20;

// "+5 000 so'm" for a delta above zero, nothing for 0
const delta = (amount: string) => (Number(amount) > 0 ? `+${formatMoney(amount, 'uz')}` : null);

// Kombo: a menu product whose parts the buyer picks in ordered slots (P6-31). The server checks every rule again and
// prices every pick; here the owner builds the slots and sees how the receipt will read.
export function ComboView() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const canEdit = access.can('menu.manage');
  const query = useCombos();
  const [editing, setEditing] = useState<Combo | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Combo | null>(null);
  const [previewing, setPreviewing] = useState<Combo | null>(null);
  const [saved, setSaved] = useState<string | null>(null);
  const toggle = useSaveCombo();
  const remove = useDeleteCombo({ onSuccess: () => setDeleting(null) });
  const fiscalMode = useSaveComboFiscalMode({ onSuccess: () => setSaved(t('combo_fiscalSaved')) });

  return (
    <>
      <PageHeader title={t('combo_title')} subtitle={t('combo_subtitle')} />
      {query.isPending ? (
        <CardSkeletons count={2} lines={4} label={t('loading')} />
      ) : query.isError ? (
        <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
      ) : (
        <div className="flex flex-col gap-4">
          <p className="text-footnote text-fg-muted">{t('combo_intro')}</p>
          {access.can('settings.manage') ? (
            <Field label={t('combo_fiscalMode')} hint={query.data.fiscal_mode === 'split' ? t('combo_fiscalSplit') : t('combo_fiscalSingle')} error={fiscalMode.error ? describeError(fiscalMode.error, t).message : undefined}>
              {(control) => (
                <select {...control} className={`${fieldClass} sm:max-w-sm`} value={query.data.fiscal_mode} disabled={fiscalMode.isPending} onChange={(event) => fiscalMode.mutate(event.target.value as ComboFiscalMode)}>
                  <option value="single">{t('combo_previewModeSingle')}</option>
                  <option value="split">{t('combo_previewModeSplit')}</option>
                </select>
              )}
            </Field>
          ) : (
            <p className="text-footnote text-fg">
              {query.data.fiscal_mode === 'split' ? t('combo_fiscalSplit') : t('combo_fiscalSingle')}{' '}
              <Link href="/fiscal" className="font-bold text-accent underline">
                {t('combo_fiscalLink')}
              </Link>
            </p>
          )}
          <Notice message={saved} />
          {canEdit && (
            <div>
              <Button icon={Plus} onClick={() => setEditing('new')}>
                {t('combo_add')}
              </Button>
            </div>
          )}
          {toggle.error && <ErrorBanner message={describeError(toggle.error, t).message} />}

          {query.data.combos.length === 0 ? (
            <EmptyState icon={Sandwich} title={t('combo_emptyTitle')} text={t('combo_emptyText')} />
          ) : (
            <ul className="grid gap-3 lg:grid-cols-2">
              {query.data.combos.map((combo) => (
                <li key={combo.id}>
                  <ComboCard
                    combo={combo}
                    canEdit={canEdit}
                    busy={toggle.isPending}
                    onToggle={(isActive) => toggle.mutate({ id: combo.id, isActive })}
                    onEdit={() => setEditing(combo)}
                    onDelete={() => setDeleting(combo)}
                    onPreview={() => setPreviewing(combo)}
                  />
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {editing !== null && query.data && (
        <ComboDialog
          combo={editing === 'new' ? null : editing}
          combos={query.data.combos}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            setSaved(t('combo_saved'));
          }}
        />
      )}
      {previewing && <PreviewDialog combo={previewing} onClose={() => setPreviewing(null)} />}
      {deleting && (
        <ConfirmDialog
          title={t('combo_deleteTitle')}
          text={t('combo_deleteText', { name: deleting.product_name ?? '' })}
          confirmLabel={t('combo_delete')}
          cancelLabel={t('menu_cancel')}
          closeLabel={t('menu_close')}
          busy={remove.isPending}
          error={remove.error ? describeError(remove.error, t).message : null}
          onConfirm={() => remove.mutate(deleting.id)}
          onClose={() => setDeleting(null)}
        />
      )}
    </>
  );
}

function ComboCard({ combo, canEdit, busy, onToggle, onEdit, onDelete, onPreview }: { combo: Combo; canEdit: boolean; busy: boolean; onToggle: (isActive: boolean) => void; onEdit: () => void; onDelete: () => void; onPreview: () => void }) {
  const { t } = useI18n();

  return (
    <Card className="flex flex-col gap-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-body font-bold text-fg">{combo.product_name}</p>
          <p className="text-footnote text-fg-muted">{combo.prices.map((price) => formatMoney(price, 'uz')).join(' · ')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Switch checked={combo.is_active} disabled={!canEdit || busy} onChange={onToggle} label={`${t('combo_active')}: ${combo.product_name ?? ''}`} />
          <span className="text-footnote text-fg">{combo.is_active ? t('combo_active') : t('combo_off')}</span>
        </div>
      </div>
      <ol className="flex flex-col gap-3">
        {combo.slots.map((slot) => (
          <li key={slot.id} className="flex flex-col gap-1 border-b border-line pb-2 last:border-b-0">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-body font-bold text-fg">{slot.name}</span>
              <Pill tone={slot.is_required ? 'info' : 'neutral'}>{slot.is_required ? t('combo_required') : t('combo_optional')}</Pill>
              <span className="text-caption text-fg-muted">{t('combo_picks', { min: slot.min_picks, max: slot.max_picks })}</span>
              {slot.allow_modifiers && <span className="text-caption text-fg-muted">{t('combo_modifiersAllowed')}</span>}
            </div>
            <ul className="flex flex-col">
              {slot.options.map((option) => (
                <li key={option.id} className="flex items-baseline justify-between gap-3 text-footnote">
                  <span className="min-w-0 text-fg">
                    {option.quantity > 1 ? `${option.quantity} × ` : ''}
                    {option.name}
                    {option.variant_name ? ` · ${option.variant_name}` : ''}
                    {option.is_default && <span className="ml-2 text-caption font-bold text-fg-muted">{t('combo_default')}</span>}
                  </span>
                  <span className="shrink-0 font-bold text-fg">{delta(option.price_delta)}</span>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ol>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" icon={ReceiptText} onClick={onPreview}>
          {t('combo_preview')}
          <span className="sr-only"> {combo.product_name}</span>
        </Button>
        {canEdit && (
          <>
            <Button variant="secondary" icon={Pencil} onClick={onEdit}>
              {t('combo_edit')}
              <span className="sr-only"> {combo.product_name}</span>
            </Button>
            <Button variant="ghost" icon={Trash2} onClick={onDelete}>
              {t('combo_delete')}
              <span className="sr-only"> {combo.product_name}</span>
            </Button>
          </>
        )}
      </div>
    </Card>
  );
}

interface OptionDraft {
  variantId: string;
  quantity: string;
  priceDelta: string;
  isDefault: boolean;
  label: string;
  modifierIds: number[];
}

interface SlotDraft {
  name: string;
  minPicks: string;
  maxPicks: string;
  allowModifiers: boolean;
  options: OptionDraft[];
}

const newOption = (): OptionDraft => ({ variantId: '', quantity: '1', priceDelta: '0', isDefault: false, label: '', modifierIds: [] });
const newSlot = (): SlotDraft => ({ name: '', minPicks: '1', maxPicks: '1', allowModifiers: false, options: [{ ...newOption(), isDefault: true }] });

const draftOf = (slot: ComboSlot): SlotDraft => ({
  name: slot.name,
  minPicks: String(slot.min_picks),
  maxPicks: String(slot.max_picks),
  allowModifiers: slot.allow_modifiers,
  options: slot.options.map((option: ComboOption) => ({
    variantId: String(option.variant_id),
    quantity: String(option.quantity),
    priceDelta: String(Number(option.price_delta)),
    isDefault: option.is_default,
    label: option.label ?? '',
    modifierIds: option.modifiers.map((modifier) => modifier.id),
  })),
});

function ComboDialog({ combo, combos, onClose, onSaved }: { combo: Combo | null; combos: Combo[]; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const groups = useModifierGroups();
  const save = useSaveCombo({ onSuccess: onSaved });
  const [productId, setProductId] = useState(combo ? String(combo.product_id) : '');
  const [slots, setSlots] = useState<SlotDraft[]>(() => (combo ? combo.slots.map(draftOf) : [newSlot()]));
  const [problem, setProblem] = useState<string | null>(null);
  const failure = save.error ? describeError(save.error, t) : null;

  const comboProducts = new Set(combos.map((existing) => existing.product_id));
  const active: Product[] = (products.data ?? []).filter((product) => product.status === 'active');
  const candidates = combo ? [] : active.filter((product) => !comboProducts.has(product.id));
  // The parts: dishes sold by the piece that are not combos themselves
  const options = active
    .filter((product) => !comboProducts.has(product.id) && String(product.id) !== productId)
    .flatMap((product) =>
      (product.variants ?? [])
        .filter((variant) => variant.is_active !== false && (variant.unit ?? 'piece') === 'piece')
        .map((variant) => ({ id: variant.id, productId: product.id, label: variant.name && variant.name !== product.name ? `${product.name} · ${variant.name}` : product.name })),
    );
  const productOfVariant = useMemo(() => new Map(options.map((option) => [String(option.id), option.productId])), [options]);
  // The modifiers a dish offers (its own groups): the preset is picked from them
  const modifiersOf = (variantId: string) => {
    const dish = productOfVariant.get(variantId);
    return (groups.data ?? [])
      .filter((group) => dish !== undefined && ((group as { product_ids?: number[] }).product_ids ?? []).includes(dish))
      .flatMap((group) => group.modifiers.map((modifier) => ({ id: modifier.id, label: `${group.name}: ${modifier.name}` })));
  };

  const updateSlot = (index: number, patch: Partial<SlotDraft>) => setSlots((current) => current.map((slot, position) => (position === index ? { ...slot, ...patch } : slot)));
  const updateOption = (slotIndex: number, optionIndex: number, patch: Partial<OptionDraft>) =>
    setSlots((current) =>
      current.map((slot, position) =>
        position !== slotIndex
          ? slot
          : {
              ...slot,
              // One default per slot: ticking one unticks the others
              options: slot.options.map((option, at) => (at === optionIndex ? { ...option, ...patch } : patch.isDefault ? { ...option, isDefault: false } : option)),
            },
      ),
    );

  const submit = () => {
    if (!combo && productId === '') return setProblem(t('menu_err_required'));
    const body: ComboSlotInput[] = [];
    for (const slot of slots) {
      const min = Number.parseInt(slot.minPicks, 10);
      const max = Number.parseInt(slot.maxPicks, 10);
      const parsed = slot.options.map((option) => ({ ...option, quantity: Number.parseInt(option.quantity, 10), priceDelta: parseDecimal(option.priceDelta) }));
      if (slot.name.trim() === '' || Number.isNaN(min) || Number.isNaN(max) || min < 0 || max < 1 || min > max || parsed.some((option) => option.variantId === '' || !(option.quantity >= 1) || option.priceDelta === null || option.priceDelta < 0)) {
        return setProblem(t('combo_invalid'));
      }
      body.push({
        name: slot.name.trim(),
        min_picks: min,
        max_picks: max,
        allow_modifiers: slot.allowModifiers,
        options: parsed.map((option) => ({
          variant_id: Number(option.variantId),
          quantity: option.quantity,
          price_delta: String(option.priceDelta),
          modifiers: option.modifierIds.map((id) => ({ modifier_id: id, quantity: 1 })),
          is_default: option.isDefault,
          label: option.label.trim() === '' ? null : option.label.trim(),
        })),
      });
    }
    setProblem(null);
    save.mutate({ id: combo?.id ?? null, productId: Number(productId), slots: body });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={combo ? t('combo_editTitle', { name: combo.product_name ?? '' }) : t('combo_add')}
      description={t('combo_dialogText')}
      busy={save.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={save.isPending} onClick={submit}>
            {t('menu_save')}
          </Button>
        </>
      }
    >
      {!combo && (
        <Field label={t('combo_product')} hint={t('combo_productHint')} error={failure?.fields.product_id}>
          {(control) => (
            <select {...control} className={fieldClass} value={productId} disabled={products.isPending} onChange={(event) => setProductId(event.target.value)}>
              <option value="">{t('combo_choose')}</option>
              {candidates.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.name}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}

      <div className="flex flex-col gap-4">
        <p className="text-body font-bold text-fg">{t('combo_slots')}</p>
        {slots.map((slot, s) => (
          <fieldset key={s} className="flex flex-col gap-3 rounded-card border border-line p-3">
            <legend className="px-1 text-footnote font-bold text-fg-muted">{t('combo_slot', { n: s + 1 })}</legend>
            <div className="grid gap-2 sm:grid-cols-[1fr_6rem_6rem]">
              <Field label={t('combo_slotName')} error={failure?.fields[`slots.${s}.name`]}>
                {(control) => <input {...control} className={fieldClass} maxLength={60} value={slot.name} placeholder={t('combo_slotNameHint')} onChange={(event) => updateSlot(s, { name: event.target.value })} />}
              </Field>
              <Field label={t('combo_min')} error={failure?.fields[`slots.${s}.min_picks`]}>
                {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={slot.minPicks} onChange={(event) => updateSlot(s, { minPicks: event.target.value })} />}
              </Field>
              <Field label={t('combo_max')} error={failure?.fields[`slots.${s}.max_picks`]}>
                {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={slot.maxPicks} onChange={(event) => updateSlot(s, { maxPicks: event.target.value })} />}
              </Field>
            </div>
            <label className="flex items-center gap-2 text-footnote text-fg">
              <input type="checkbox" checked={slot.allowModifiers} onChange={(event) => updateSlot(s, { allowModifiers: event.target.checked })} />
              {t('combo_allowModifiers')}
            </label>

            {slot.options.map((option, o) => {
              const modifiers = modifiersOf(option.variantId);
              const key = `slots.${s}.options.${o}`;
              return (
                <div key={o} className="grid gap-2 rounded-card bg-surface-2 p-2 sm:grid-cols-[1fr_4rem_7rem]">
                  <Field label={t('combo_option')} error={failure?.fields[`${key}.variant_id`]}>
                    {(control) => (
                      <select {...control} className={fieldClass} value={option.variantId} onChange={(event) => updateOption(s, o, { variantId: event.target.value, modifierIds: [] })}>
                        <option value="">{t('combo_choose')}</option>
                        {options.map((choice) => (
                          <option key={choice.id} value={choice.id}>
                            {choice.label}
                          </option>
                        ))}
                      </select>
                    )}
                  </Field>
                  <Field label={t('combo_optionQty')} error={failure?.fields[`${key}.quantity`]}>
                    {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={option.quantity} onChange={(event) => updateOption(s, o, { quantity: event.target.value })} />}
                  </Field>
                  <Field label={t('combo_optionDelta')} error={failure?.fields[`${key}.price_delta`]}>
                    {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={option.priceDelta} onChange={(event) => updateOption(s, o, { priceDelta: event.target.value })} />}
                  </Field>
                  <Field label={t('combo_optionLabel')} error={failure?.fields[`${key}.label`]}>
                    {(control) => <input {...control} className={fieldClass} maxLength={80} value={option.label} placeholder={t('combo_optionLabelHint')} onChange={(event) => updateOption(s, o, { label: event.target.value })} />}
                  </Field>
                  <label className="flex items-end gap-2 pb-2 text-footnote text-fg">
                    <input type="radio" name={`combo-default-${s}`} checked={option.isDefault} onChange={() => updateOption(s, o, { isDefault: true })} />
                    {t('combo_optionDefault')}
                  </label>
                  <div className="flex items-end">
                    <Button variant="ghost" icon={Trash2} disabled={slot.options.length === 1} onClick={() => updateSlot(s, { options: slot.options.filter((_, at) => at !== o) })}>
                      <span className="sr-only">{t('combo_optionRemove')}</span>
                    </Button>
                  </div>
                  {modifiers.length > 0 && (
                    <fieldset className="flex flex-wrap gap-3 sm:col-span-3">
                      <legend className="text-caption text-fg-muted">{t('combo_optionModifiers')}</legend>
                      {modifiers.map((modifier) => (
                        <label key={modifier.id} className="flex items-center gap-1 text-footnote text-fg">
                          <input
                            type="checkbox"
                            checked={option.modifierIds.includes(modifier.id)}
                            onChange={(event) => updateOption(s, o, { modifierIds: event.target.checked ? [...option.modifierIds, modifier.id] : option.modifierIds.filter((id) => id !== modifier.id) })}
                          />
                          {modifier.label}
                        </label>
                      ))}
                    </fieldset>
                  )}
                  {failure?.fields[`${key}.modifiers.0`] && <p className="text-caption text-danger sm:col-span-3">{failure.fields[`${key}.modifiers.0`]}</p>}
                </div>
              );
            })}
            {failure?.fields[`slots.${s}.options`] && <p className="text-caption text-danger">{failure.fields[`slots.${s}.options`]}</p>}
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" icon={Plus} disabled={slot.options.length >= MAX_OPTIONS} onClick={() => updateSlot(s, { options: [...slot.options, newOption()] })}>
                {t('combo_optionAdd')}
              </Button>
              <Button variant="ghost" icon={Trash2} disabled={slots.length === 1} onClick={() => setSlots((current) => current.filter((_, at) => at !== s))}>
                {t('combo_slotRemove')}
              </Button>
            </div>
          </fieldset>
        ))}
        <div>
          <Button variant="secondary" icon={Plus} disabled={slots.length >= MAX_SLOTS} onClick={() => setSlots((current) => [...current, newSlot()])}>
            {t('combo_slotAdd')}
          </Button>
        </div>
      </div>

      {problem && <ErrorBanner message={problem} />}
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}

// The picks of the preview: each slot's default (left out: the server takes it), changed by the selects
function PreviewDialog({ combo, onClose }: { combo: Combo; onClose: () => void }) {
  const { t } = useI18n();
  const [chosen, setChosen] = useState<Record<number, string>>({});
  const picks: ComboPick[] = combo.slots.flatMap((slot) => (chosen[slot.id] ? (chosen[slot.id] === 'none' ? [] : [{ option_id: chosen[slot.id] as string }]) : []));
  const preview = useComboPreview(combo.id, picks);

  return (
    <Dialog open onClose={onClose} title={t('combo_previewTitle', { name: combo.product_name ?? '' })} description={t('combo_previewText')} closeLabel={t('menu_close')} footer={<Button onClick={onClose}>{t('menu_close')}</Button>}>
      <div className="grid gap-2 sm:grid-cols-2">
        {combo.slots.map((slot) => (
          <Field key={slot.id} label={slot.name}>
            {(control) => (
              <select {...control} className={fieldClass} value={chosen[slot.id] ?? ''} onChange={(event) => setChosen((current) => ({ ...current, [slot.id]: event.target.value }))}>
                <option value="">{slot.options.find((option) => option.is_default)?.name ?? (slot.is_required ? t('combo_choose') : t('combo_previewNone'))}</option>
                {!slot.is_required && <option value="none">{t('combo_previewNone')}</option>}
                {slot.options.map((option) => (
                  <option key={option.uuid} value={option.uuid}>
                    {option.name}
                    {delta(option.price_delta) ? ` (${delta(option.price_delta)})` : ''}
                  </option>
                ))}
              </select>
            )}
          </Field>
        ))}
      </div>

      {preview.isPending ? (
        <CardSkeletons count={1} lines={3} label={t('loading')} />
      ) : preview.isError ? (
        <ErrorBanner message={describeError(preview.error, t).message} />
      ) : (
        <div className="flex flex-col gap-4">
          <section aria-label={t('combo_previewGuest')} className="rounded-card border border-line p-3 font-mono text-footnote">
            <p className="mb-2 font-sans text-caption font-bold text-fg-muted">{t('combo_previewGuest')}</p>
            <p className="flex justify-between gap-3 font-bold text-fg">
              <span>
                {preview.data.quantity} × {preview.data.name}
              </span>
              <span>{formatMoney(preview.data.price, 'uz')}</span>
            </p>
            <ul>
              {preview.data.parts.map((part, index) => (
                <li key={index} className="pl-3 text-fg-muted">
                  {part.label}
                </li>
              ))}
            </ul>
          </section>
          <section aria-label={t('combo_previewFiscal')} className="flex flex-col gap-2">
            <p className="text-caption font-bold text-fg-muted">{t('combo_previewFiscal')}</p>
            <FiscalLines title={t('combo_previewModeSingle')} current={preview.data.fiscal_mode === 'single'} lines={preview.data.fiscal.single} />
            <FiscalLines title={t('combo_previewModeSplit')} current={preview.data.fiscal_mode === 'split'} lines={preview.data.fiscal.split} />
          </section>
        </div>
      )}
    </Dialog>
  );
}

function FiscalLines({ title, current, lines }: { title: string; current: boolean; lines: ComboPreviewLine[] }) {
  const { t } = useI18n();
  const total = lines.reduce((sum, line) => sum + Math.round(Number(line.amount) * 100), 0) / 100;

  return (
    <div className="rounded-card border border-line p-3 text-footnote">
      <p className="mb-1 font-bold text-fg">
        {title}
        {current && <span className="ml-2 text-caption text-fg-muted">({t('combo_previewCurrent')})</span>}
      </p>
      <table className="w-full">
        <tbody>
          {lines.map((line, index) => (
            <tr key={index}>
              <td className="text-fg">
                {line.quantity} × {line.name}
              </td>
              <td className="text-fg-muted">{line.mxik_code ?? '—'}</td>
              <td className="text-right font-bold text-fg">{formatMoney(line.amount, 'uz')}</td>
            </tr>
          ))}
          <tr>
            <td colSpan={2} className="pt-1 text-fg-muted">
              {t('combo_previewTotal')}
            </td>
            <td className="pt-1 text-right font-bold text-fg">{formatMoney(total.toFixed(2), 'uz')}</td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}
