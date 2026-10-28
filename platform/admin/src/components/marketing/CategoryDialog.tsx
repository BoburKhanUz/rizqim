'use client';

import { useState } from 'react';
import { Field, fieldClass, Switch } from '@rizqim/ui';
import { useCreateCategory, useUpdateCategory, type Category, type CategoryInput } from '@/lib/api/marketing';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { ImageField, ReasonDialog } from './MarketingParts';

// A new category or the change of one (marketing.manage + a reason). Two levels only: the parent is a top-level category, and a
// category that has sub-categories stays on top. The icon is optional; "hide" is the switch (the products stay).

export function CategoryDialog({ category, all, onDone, onClose }: { category: Category | null; all: Category[]; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const create = useCreateCategory();
  const update = useUpdateCategory();
  const mutation = category ? update : create;

  const [name, setName] = useState(category?.name ?? '');
  const [parentId, setParentId] = useState(category?.parent ? String(category.parent.id) : '');
  const [active, setActive] = useState(category?.is_active ?? true);
  const [image, setImage] = useState<File | null>(null);
  const [removeImage, setRemoveImage] = useState(false);

  // A parent is a top-level category other than this one; a category with sub-categories cannot go inside another
  const parents = all.filter((candidate) => candidate.parent === null && candidate.id !== category?.id);
  const canHaveParent = !category || category.children_count === 0;
  const valid = name.trim().length >= 2;

  const changes = (): Omit<CategoryInput, 'reason'> => {
    const parent = parentId === '' ? null : Number(parentId);
    if (!category) return { name: name.trim(), parent_id: parent, is_active: active, image };
    const result: Omit<CategoryInput, 'reason'> = {};
    if (name.trim() !== category.name) result.name = name.trim();
    if (parent !== (category.parent?.id ?? null)) result.parent_id = parent;
    if (active !== category.is_active) result.is_active = active;
    if (image) result.image = image;
    else if (removeImage) result.remove_image = true;
    return result;
  };
  const changed = category === null || Object.keys(changes()).length > 0;

  const submit = (reason: string) => {
    const body = { ...changes(), reason };
    if (category) update.mutate({ id: category.id, ...body }, { onSuccess: () => onDone(t('marketing_category_saved')) });
    else create.mutate({ ...body, name: name.trim() }, { onSuccess: () => onDone(t('marketing_category_created')) });
  };

  return (
    <ReasonDialog
      title={category ? t('marketing_category_editTitle') : t('marketing_category_newTitle')}
      text={t('marketing_category_dialogText')}
      submitLabel={t('save')}
      busy={mutation.isPending}
      error={mutation.error ? errorText(mutation.error, t) : null}
      reasonError={fieldError(mutation.error, 'reason')}
      canSubmit={valid && changed}
      onClose={onClose}
      onSubmit={submit}
    >
      <Field label={t('marketing_category_name')} error={fieldError(mutation.error, 'name')}>
        {(control) => <input {...control} data-autofocus type="text" className={fieldClass} value={name} maxLength={255} onChange={(event) => setName(event.target.value)} />}
      </Field>
      <Field label={t('marketing_category_parent')} hint={canHaveParent ? t('marketing_category_parentHint') : t('marketing_category_parentLocked')} error={fieldError(mutation.error, 'parent_id')}>
        {(control) => (
          <select {...control} className={fieldClass} value={parentId} disabled={!canHaveParent} onChange={(event) => setParentId(event.target.value)}>
            <option value="">{t('marketing_category_topLevel')}</option>
            {parents.map((parent) => (
              <option key={parent.id} value={parent.id}>
                {parent.name}
              </option>
            ))}
          </select>
        )}
      </Field>
      <ImageField
        label={t('marketing_category_icon')}
        hint={t('marketing_imageHint')}
        current={removeImage ? null : category?.image_preview}
        currentLabel={category ? t('marketing_category_iconOf', { name: category.name }) : undefined}
        file={image}
        onFile={(file) => {
          setImage(file);
          if (file) setRemoveImage(false);
        }}
        error={fieldError(mutation.error, 'image')}
      />
      {category?.image_preview && !image && <Switch checked={removeImage} onChange={setRemoveImage} label={t('marketing_category_removeIcon')} />}
      <Switch checked={active} onChange={setActive} label={t('marketing_category_active')} />
    </ReasonDialog>
  );
}
