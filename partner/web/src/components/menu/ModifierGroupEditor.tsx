'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Layers, Plus, Trash2 } from 'lucide-react';
import { Button, buttonClasses, Card, CardSkeletons, EmptyState, Field, fieldClass, IconButton, InlineError, Section } from '@rizqim/ui';
import { useModifierGroups, useSaveModifierGroup, type ModifierGroup, type ModifierGroupInput } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { PageHeader } from '../PageHeader';
import { useMenuAccess } from './access';
import { ErrorBanner, SuccessNote } from './ErrorBanner';
import { GroupProducts } from './GroupProducts';
import { parseDecimal, toInputText } from './numbers';
import { describeError, unplacedMessages } from './serverError';
import { Switch } from './Switch';

interface OptionDraft {
  key: string;
  id?: number;
  name: string;
  ru: string;
  price: string;
  maxQuantity: string;
  active: boolean;
}

interface GroupDraft {
  name: string;
  ru: string;
  min: string;
  max: string;
  required: boolean;
  sortOrder: string;
  active: boolean;
  options: OptionDraft[];
}

let counter = 0;
const newOption = (): OptionDraft => ({ key: `option-${++counter}`, name: '', ru: '', price: '', maxQuantity: '1', active: true });

function draftFrom(group: ModifierGroup | null): GroupDraft {
  if (!group) return { name: '', ru: '', min: '0', max: '', required: false, sortOrder: '', active: true, options: [newOption()] };
  return {
    name: group.name,
    ru: group.name_i18n?.ru ?? '',
    min: String(group.min_select),
    max: group.max_select === null ? '' : String(group.max_select),
    required: group.is_required,
    sortOrder: String(group.sort_order),
    active: group.is_active,
    options: group.modifiers.map((modifier) => ({
      key: `option-${++counter}`,
      id: modifier.id,
      name: modifier.name,
      ru: modifier.name_i18n?.ru ?? '',
      price: Number(modifier.price_delta) === 0 ? '' : toInputText(modifier.price_delta),
      maxQuantity: String(modifier.max_quantity),
      active: modifier.is_active,
    })),
  };
}

const KNOWN_FIELDS = /^(name|name_i18n(\..*)?|min_select|max_select|sort_order|modifiers\.\d+\.(name|price_delta|max_quantity))$/;

// /menu/groups/new and /menu/groups/{id}: a group ("Sous tanlang") with its options and their price deltas
export function ModifierGroupEditor({ id }: { id: string }) {
  const { t } = useI18n();
  const access = useMenuAccess();
  const isNew = id === 'new';
  const query = useModifierGroups();
  const back = (
    <Link href="/menu" className={buttonClasses({ variant: 'ghost' })}>
      <ArrowLeft aria-hidden="true" className="size-[18px]" />
      {t('nav_menu')}
    </Link>
  );
  const title = isNew ? t('menu_add_group') : t('menu_edit_group');

  if (query.isPending) {
    return (
      <>
        <PageHeader title={title} action={back} />
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      </>
    );
  }
  if (query.isError) {
    return (
      <>
        <PageHeader title={title} action={back} />
        <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
      </>
    );
  }
  const group = isNew ? null : (query.data.find((item) => String(item.id) === id) ?? null);
  if (!isNew && !group) {
    return (
      <>
        <PageHeader title={title} action={back} />
        <EmptyState icon={Layers} title={t('menu_group_not_found')} action={<Link href="/menu" className={buttonClasses()}>{t('nav_menu')}</Link>} />
      </>
    );
  }
  const canManage = access.can('menu.manage');
  return (
    <>
      <PageHeader title={group ? group.name : t('menu_add_group')} subtitle={group ? t('menu_edit_group') : undefined} action={back} />
      <div className="flex flex-col gap-8">
        <GroupForm key={group?.id ?? 'new'} group={group} canManage={canManage} />
        {group ? (
          <Section title={t('menu_section_group_products')}>
            <GroupProducts key={group.product_ids.join(',')} group={group} canManage={canManage} />
          </Section>
        ) : (
          <p className="text-footnote text-fg-muted">{t('menu_save_group_first')}</p>
        )}
      </div>
    </>
  );
}

function GroupForm({ group, canManage }: { group: ModifierGroup | null; canManage: boolean }) {
  const { t } = useI18n();
  const router = useRouter();
  const [draft, setDraft] = useState(() => draftFrom(group));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [banner, setBanner] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const save = useSaveModifierGroup();

  const patch = (changes: Partial<GroupDraft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(false);
  };
  const patchOption = (index: number, changes: Partial<OptionDraft>) => patch({ options: draft.options.map((option, i) => (i === index ? { ...option, ...changes } : option)) });

  const check = (): Record<string, string> => {
    const found: Record<string, string> = {};
    if (draft.name.trim() === '') found.name = t('menu_err_required');
    for (const [field, text] of [['min_select', draft.min], ['max_select', draft.max], ['sort_order', draft.sortOrder]] as const) {
      if (text.trim() !== '' && parseDecimal(text) === null) found[field] = t('menu_err_number');
    }
    draft.options.forEach((option, index) => {
      if (option.name.trim() === '') found[`modifiers.${index}.name`] = t('menu_err_required');
      if (option.price.trim() !== '' && parseDecimal(option.price) === null) found[`modifiers.${index}.price_delta`] = t('menu_err_number');
      if (option.maxQuantity.trim() !== '' && parseDecimal(option.maxQuantity) === null) found[`modifiers.${index}.max_quantity`] = t('menu_err_number');
    });
    return found;
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const problems = check();
    setErrors(problems);
    setSaved(false);
    if (Object.keys(problems).length > 0) return setBanner(t('menu_check_fields'));
    setBanner(null);

    const input: ModifierGroupInput = {
      name: draft.name.trim(),
      name_i18n: draft.ru.trim() === '' ? null : { ru: draft.ru.trim() },
      min_select: parseDecimal(draft.min) ?? 0,
      max_select: parseDecimal(draft.max),
      is_required: draft.required,
      sort_order: parseDecimal(draft.sortOrder) ?? 0,
      is_active: draft.active,
      modifiers: draft.options.map((option, index) => ({
        ...(option.id !== undefined ? { id: option.id } : {}),
        name: option.name.trim(),
        name_i18n: option.ru.trim() === '' ? null : { ru: option.ru.trim() },
        price_delta: parseDecimal(option.price) ?? 0,
        max_quantity: parseDecimal(option.maxQuantity) ?? 1,
        sort_order: index,
        is_active: option.active,
      })),
    };
    try {
      const result = await save.mutateAsync({ id: group?.id ?? null, input });
      if (!group) return router.replace(`/menu/groups/${result.id}`);
      setDraft(draftFrom(result));
      setSaved(true);
    } catch (error) {
      const view = describeError(error, t);
      setErrors(view.fields);
      setBanner(view.message);
    }
  };

  const unplaced = unplacedMessages(errors, KNOWN_FIELDS);

  return (
    <form onSubmit={(event) => void submit(event)} noValidate className="flex flex-col gap-4">
      <fieldset disabled={!canManage || save.isPending} className="flex min-w-0 flex-col gap-4 border-0 p-0">
        <Card className="flex flex-col gap-4">
          <h2 className="text-headline font-bold text-fg">{t('menu_section_main')}</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label={t('menu_group_name')} error={errors.name}>
              {(control) => <input {...control} className={fieldClass} value={draft.name} maxLength={255} onChange={(event) => patch({ name: event.target.value })} />}
            </Field>
            <Field label={t('menu_group_name_ru')} error={errors['name_i18n.ru']}>
              {(control) => <input {...control} className={fieldClass} value={draft.ru} maxLength={255} onChange={(event) => patch({ ru: event.target.value })} />}
            </Field>
            <Field label={t('menu_min_select')} error={errors.min_select}>
              {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={draft.min} onChange={(event) => patch({ min: event.target.value })} />}
            </Field>
            <Field label={t('menu_max_select')} hint={t('menu_max_select_hint')} error={errors.max_select}>
              {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={draft.max} onChange={(event) => patch({ max: event.target.value })} />}
            </Field>
            <Field label={t('menu_sort_order')} error={errors.sort_order}>
              {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={draft.sortOrder} onChange={(event) => patch({ sortOrder: event.target.value })} />}
            </Field>
          </div>
          <div className="flex flex-wrap gap-x-8 gap-y-2">
            <div className="flex items-center gap-2">
              <Switch checked={draft.required} onChange={(required) => patch({ required })} label={t('menu_required_choice')} />
              <span className="text-body text-fg">
                {t('menu_required_choice')}
                <span className="block text-footnote text-fg-muted">{t('menu_required_choice_hint')}</span>
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Switch checked={draft.active} onChange={(active) => patch({ active })} label={t('menu_is_active')} />
              <span className="text-body text-fg">{t('menu_is_active')}</span>
            </div>
          </div>
        </Card>

        <Card className="flex flex-col gap-4">
          <h2 className="text-headline font-bold text-fg">{t('menu_section_options')}</h2>
          {draft.options.map((option, index) => (
            <div key={option.key} role="group" aria-label={t('menu_option_n', { n: index + 1 })} className="flex flex-col gap-3 rounded-card border border-line p-3">
              <div className="flex items-center justify-between gap-2">
                <h3 className="text-body font-bold text-fg">{t('menu_option_n', { n: index + 1 })}</h3>
                <IconButton icon={Trash2} label={`${t('menu_remove_option')}: ${index + 1}`} onClick={() => patch({ options: draft.options.filter((_, i) => i !== index) })} />
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label={t('menu_option_name')} error={errors[`modifiers.${index}.name`]}>
                  {(control) => <input {...control} className={fieldClass} value={option.name} maxLength={255} onChange={(event) => patchOption(index, { name: event.target.value })} />}
                </Field>
                <Field label={t('menu_option_name_ru')}>
                  {(control) => <input {...control} className={fieldClass} value={option.ru} maxLength={255} onChange={(event) => patchOption(index, { ru: event.target.value })} />}
                </Field>
                <Field label={t('menu_price_delta')} hint={t('menu_price_delta_hint')} error={errors[`modifiers.${index}.price_delta`]}>
                  {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={option.price} onChange={(event) => patchOption(index, { price: event.target.value })} />}
                </Field>
                <Field label={t('menu_max_quantity')} error={errors[`modifiers.${index}.max_quantity`]}>
                  {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={option.maxQuantity} onChange={(event) => patchOption(index, { maxQuantity: event.target.value })} />}
                </Field>
              </div>
              <div className="flex items-center gap-2">
                <Switch checked={option.active} onChange={(active) => patchOption(index, { active })} label={`${t('menu_is_active')}: ${option.name || index + 1}`} />
                <span className="text-body text-fg">{t('menu_is_active')}</span>
              </div>
            </div>
          ))}
          {errors.modifiers && (
            <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
              {errors.modifiers}
            </p>
          )}
          <div>
            <Button variant="secondary" icon={Plus} onClick={() => patch({ options: [...draft.options, newOption()] })}>
              {t('menu_add_option')}
            </Button>
          </div>
          <p className="text-footnote text-fg-muted">{t('menu_options_replace_hint')}</p>
        </Card>
      </fieldset>

      {banner && <ErrorBanner message={banner} details={unplaced} />}
      {saved && <SuccessNote>{t('menu_saved')}</SuccessNote>}
      {canManage ? (
        <div>
          <Button type="submit" size="lg" loading={save.isPending}>
            {group ? t('menu_save') : t('menu_create')}
          </Button>
        </div>
      ) : (
        <p className="text-footnote text-fg-muted">{t('menu_read_only')}</p>
      )}
    </form>
  );
}
