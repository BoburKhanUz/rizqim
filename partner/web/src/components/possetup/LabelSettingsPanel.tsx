'use client';

import { useState, type FormEvent } from 'react';
import { Save } from 'lucide-react';
import { Button, Card, FormError, Notice } from '@rizqim/ui';
import { LABEL_SCOPES, LABEL_SIZES, useLabelSettings, useOwnerCategories, useSaveLabelSettings, type LabelScopeKey, type LabelSettings, type LabelSettingsPatch, type LabelSizeKey } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check } from '../management/fields';
import { Gate } from '../pos/Gate';
import { SelectField } from '../pos/fields';
import { changedKeys, useServerDraft } from '../pos/serverDraft';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// The sticker and label settings of a branch (P6-32; docs/API.md "Stiker va yorliq"): the label roll size, which items get a cup
// sticker (every item or only the chosen categories), one sticker per piece, a bag label for takeaway orders. The form sends
// only what changed (the server keeps the rest).

interface Draft {
  size: LabelSizeKey;
  scope: LabelScopeKey;
  categoryIds: number[];
  perUnit: boolean;
  bagLabel: boolean;
}

function draftOf(settings: LabelSettings): Draft {
  return {
    size: (LABEL_SIZES as readonly string[]).includes(settings.size) ? settings.size : '40x30',
    scope: (LABEL_SCOPES as readonly string[]).includes(settings.scope) ? settings.scope : 'all',
    categoryIds: [...settings.category_ids].sort((a, b) => a - b),
    perUnit: settings.per_unit,
    bagLabel: settings.bag_label,
  };
}

const SERVER_FIELDS = ['size', 'scope', 'category_ids', 'per_unit', 'bag_label'];

export function LabelSettingsPanel({ branchId, enabled }: { branchId: number; enabled: boolean }) {
  const { t } = useI18n();
  const settings = useLabelSettings(enabled ? branchId : null);
  if (!enabled) return <p role="note" className="rounded-control bg-info-soft px-3 py-2 text-footnote font-bold text-info-soft-fg">{t('qs_lbl_moduleOff')}</p>;
  return <Gate query={settings} count={3}>{(data) => <LabelForm key={branchId} branchId={branchId} data={data} />}</Gate>;
}

// A sketch of the 40 x 30 cup sticker (the cashier app prints the real one): shows what the guest gets
function StickerPreview({ size }: { size: LabelSizeKey }) {
  const { t } = useI18n();
  const wide = size === '58x40';
  return (
    <figure aria-label={t('qs_lbl_preview')} className="flex flex-col gap-2">
      <div
        data-testid="sticker-preview"
        className="flex flex-col justify-between rounded-control border border-line bg-surface p-2 font-mono text-footnote text-fg"
        style={{ width: wide ? 232 : 160, height: wide ? 160 : 120 }}
      >
        <div className="flex items-start justify-between">
          <span className="text-title font-bold">#57</span>
          <span>12:15</span>
        </div>
        <div>
          <p className="font-bold">Latte</p>
          <p>0,4 l</p>
          <p>+ Sut 0%, Vanil sirop</p>
        </div>
        <p className="font-bold">Olib ketish</p>
      </div>
      <figcaption className="text-footnote text-fg-muted">{t('qs_lbl_preview')}: {t(`qs_lbl_size_${size}`)}</figcaption>
    </figure>
  );
}

function LabelForm({ branchId, data }: { branchId: number; data: LabelSettings }) {
  const { t } = useI18n();
  const save = useSaveLabelSettings();
  const categories = useOwnerCategories();
  const { draft, setDraft, base, dirty } = useServerDraft(data, draftOf);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<string | null>(null);

  const patch = (changes: Partial<Draft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(null);
  };
  const toggleCategory = (id: number, on: boolean) => patch({ categoryIds: (on ? [...draft.categoryIds, id] : draft.categoryIds.filter((item) => item !== id)).sort((a, b) => a - b) });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    const problems: Record<string, string> = {};
    if (draft.scope === 'categories' && draft.categoryIds.length === 0) problems.categoryIds = t('qs_lbl_pickCategory');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    // Only what changed: a key left out keeps what the server has
    const changed = new Set(changedKeys(draft, base));
    const body: LabelSettingsPatch = {};
    if (changed.has('size')) body.size = draft.size;
    if (changed.has('scope')) body.scope = draft.scope;
    // The categories travel with the scope: `categories` needs them, `all` clears them
    if (changed.has('scope') || changed.has('categoryIds')) {
      if (draft.scope === 'categories') body.category_ids = draft.categoryIds;
    }
    if (changed.has('perUnit')) body.per_unit = draft.perUnit;
    if (changed.has('bagLabel')) body.bag_label = draft.bagLabel;

    save.mutate(
      { branchId, patch: body },
      {
        onSuccess: (answer) => {
          setDraft(draftOf(answer));
          setSaved(t('qs_lbl_saved'));
        },
        onError: (error) => setErrors(sortServerErrors(error, SERVER_FIELDS, t)),
      },
    );
  };

  const serverError = (field: string) => errors.fields[field];
  const list = categories.data ?? [];

  return (
    <form onSubmit={submit} noValidate className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('qs_lbl_intro')}</p>
      <Notice message={saved} />

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('qs_lbl_sizeTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('qs_lbl_sizeHint')}</p>
        </div>
        <div className="flex flex-wrap items-end gap-6">
          <SelectField label={t('qs_lbl_size')} value={draft.size} onChange={(size) => patch({ size })} options={LABEL_SIZES.map((value) => ({ value, label: t(`qs_lbl_size_${value}`) }))} error={serverError('size')} className="min-w-48" />
          <StickerPreview size={draft.size} />
        </div>
      </Card>

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('qs_lbl_scopeTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('qs_lbl_scopeHint')}</p>
        </div>
        <SelectField label={t('qs_lbl_scope')} value={draft.scope} onChange={(scope) => patch({ scope })} options={LABEL_SCOPES.map((value) => ({ value, label: t(`qs_lbl_scope_${value}`) }))} error={serverError('scope')} className="sm:max-w-sm" />
        {draft.scope === 'categories' && (
          <fieldset className="flex min-w-0 flex-col gap-1 border-0 p-0">
            <legend className="text-body font-bold text-fg">{t('qs_lbl_categories')}</legend>
            {categories.isSuccess && list.length === 0 && <p className="text-footnote text-fg-muted">{t('qs_lbl_noCategories')}</p>}
            <div className="grid gap-x-6 sm:grid-cols-2">
              {list.map((category) => (
                <Check key={category.id} checked={draft.categoryIds.includes(category.id)} onChange={(on) => toggleCategory(category.id, on)} label={category.name} />
              ))}
            </div>
            {(local.categoryIds ?? serverError('category_ids')) && (
              <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
                {local.categoryIds ?? serverError('category_ids')}
              </p>
            )}
          </fieldset>
        )}
        <Check checked={draft.perUnit} onChange={(perUnit) => patch({ perUnit })} label={t('qs_lbl_perUnit')} hint={serverError('per_unit') ?? t('qs_lbl_perUnitHint')} />
        <Check checked={draft.bagLabel} onChange={(bagLabel) => patch({ bagLabel })} label={t('qs_lbl_bag')} hint={serverError('bag_label') ?? t('qs_lbl_bagHint')} />
      </Card>

      <FormError message={errors.form} />
      <div>
        <Button type="submit" size="lg" icon={Save} loading={save.isPending} disabled={!dirty}>
          {t('qs_lbl_save')}
        </Button>
      </div>
    </form>
  );
}
