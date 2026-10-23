'use client';

import { useEffect, useRef, useState, type FormEvent } from 'react';
import { ImagePlus, X } from 'lucide-react';
import { Button, Card, Field, fieldClass } from '@rizqim/ui';
import { productEditorFormData, useSaveProduct, type Category, type Product } from '@rizqim/staff';
import { useT } from '../I18nProvider';
import { checkFiscalDraft, fiscalChanges, fiscalDraftFrom, type FiscalDraft } from '../fiscal/productFiscal';
import { ProductFiscalFields } from '../fiscal/ProductFiscalFields';
import { ErrorBanner, SuccessNote } from './ErrorBanner';
import { parseDecimal } from './numbers';
import { ProductThumb } from './ProductThumb';
import { describeError, unplacedMessages } from './serverError';
import { Switch } from './Switch';
import { emptyVariant, variantFromApi, VariantFields, type VariantDraft } from './VariantFields';

const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const KNOWN_FIELDS = /^(name|description|category_id|sort_order|image|kind|mxik_code|package_code|vat_rate|variants(\.\d+\.(name|price|unit|quantity_step|min_quantity|batch_consumption|unit_label))?)$/;

interface Draft {
  name: string;
  description: string;
  categoryId: string;
  isActive: boolean;
  batchTracked: boolean;
  quickAdd: boolean;
  sortOrder: string;
  variants: VariantDraft[];
  fiscal: FiscalDraft;
}

function draftFrom(product: Product | null, defaultVariantName: string): Draft {
  if (!product) return { name: '', description: '', categoryId: '', isActive: true, batchTracked: false, quickAdd: false, sortOrder: '', variants: [emptyVariant(defaultVariantName)], fiscal: fiscalDraftFrom(null) };
  return {
    name: product.name,
    description: product.description ?? '',
    categoryId: String(product.category_id),
    isActive: product.is_active,
    batchTracked: product.batch_tracked,
    quickAdd: product.quick_add,
    sortOrder: String(product.sort_order),
    variants: (product.variants ?? []).length > 0 ? (product.variants ?? []).map(variantFromApi) : [emptyVariant(defaultVariantName)],
    fiscal: fiscalDraftFrom(product),
  };
}

interface ProductFormProps {
  product: Product | null;
  categories: Category[];
  canManage: boolean;
  // A product just created: the page moves to its editor
  onCreated: (product: Product) => void;
}

// The product itself: name, description, category, picture, variants and the switches. Sent as one multipart request;
// what the server refuses shows under the field it names, in Uzbek.
export function ProductForm({ product, categories, canManage, onCreated }: ProductFormProps) {
  const t = useT();
  const [draft, setDraft] = useState<Draft>(() => draftFrom(product, t('menu_variant_default')));
  // The fiscal fields as the product had them: only what the person changed is sent
  const [initialFiscal, setInitialFiscal] = useState<FiscalDraft>(() => fiscalDraftFrom(product));
  const [image, setImage] = useState<{ file: File; preview: string | null } | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [banner, setBanner] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);
  const previewRef = useRef<string | null>(null);

  useEffect(
    () => () => {
      if (previewRef.current) URL.revokeObjectURL(previewRef.current);
    },
    [],
  );

  const save = useSaveProduct();
  const patch = (changes: Partial<Draft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(false);
  };

  const pickImage = (file: File | undefined) => {
    if (!file) return;
    if (!file.type.startsWith('image/')) return setErrors((current) => ({ ...current, image: t('menu_err_image') }));
    if (file.size > MAX_IMAGE_BYTES) return setErrors((current) => ({ ...current, image: t('menu_image_too_big') }));
    if (previewRef.current) URL.revokeObjectURL(previewRef.current);
    previewRef.current = typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : null;
    setImage({ file, preview: previewRef.current });
    setErrors((current) => Object.fromEntries(Object.entries(current).filter(([key]) => key !== 'image')));
    setSaved(false);
  };

  const clearImage = () => {
    if (previewRef.current) URL.revokeObjectURL(previewRef.current);
    previewRef.current = null;
    setImage(null);
    if (fileInput.current) fileInput.current.value = '';
  };

  // The light checks: something is there and the numbers are numbers. Whether they are acceptable is the server's call.
  const check = (): Record<string, string> => {
    const found: Record<string, string> = {};
    if (draft.name.trim() === '') found.name = t('menu_err_required');
    if (draft.categoryId === '') found.category_id = t('menu_err_required');
    if (draft.sortOrder.trim() !== '' && parseDecimal(draft.sortOrder) === null) found.sort_order = t('menu_err_number');
    Object.assign(found, checkFiscalDraft(draft.fiscal, t));
    draft.variants.forEach((variant, index) => {
      if (variant.name.trim() === '') found[`variants.${index}.name`] = t('menu_err_required');
      if (parseDecimal(variant.price) === null) found[`variants.${index}.price`] = variant.price.trim() === '' ? t('menu_err_required') : t('menu_err_number');
      const optional: Array<[string, string]> = [['quantity_step', variant.step], ['min_quantity', variant.min], ['menu_batch_consumption', variant.consumption]];
      for (const [field, text] of optional) if (text.trim() !== '' && parseDecimal(text) === null) found[`variants.${index}.${field}`] = t('menu_err_number');
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

    const variants = draft.variants.map((variant) => {
      const measured = variant.unit !== 'piece';
      return {
        ...(variant.id !== undefined ? { id: variant.id } : {}),
        name: variant.name.trim(),
        price: parseDecimal(variant.price),
        unit: variant.unit,
        // Empty: the server's default step / minimum
        quantity_step: measured ? parseDecimal(variant.step) : null,
        min_quantity: measured ? parseDecimal(variant.min) : null,
        ...(variant.consumption.trim() !== '' ? { batch_consumption: parseDecimal(variant.consumption) } : {}),
        // The unit on the receipt: sent when it has a text, or to clear one the variant had
        ...(variant.unitLabel.trim() !== '' ? { unit_label: variant.unitLabel.trim() } : variant.unitLabelWas ? { unit_label: null } : {}),
      };
    });
    const form = productEditorFormData({
      name: draft.name.trim(),
      description: draft.description.trim(),
      category_id: draft.categoryId,
      is_active: draft.isActive,
      batch_tracked: draft.batchTracked,
      quick_add: draft.quickAdd,
      sort_order: parseDecimal(draft.sortOrder),
      image: image?.file ?? null,
      variants,
      ...fiscalChanges(initialFiscal, draft.fiscal),
    });

    try {
      const result = await save.mutateAsync({ id: product?.id ?? null, form });
      if (!product && result) return onCreated(result);
      if (result?.variants) {
        setDraft(draftFrom(result, t('menu_variant_default')));
        setInitialFiscal(fiscalDraftFrom(result));
      }
      clearImage();
      setSaved(true);
    } catch (error) {
      const view = describeError(error, t);
      setErrors(view.fields);
      setBanner(view.message);
    }
  };

  const unplaced = unplacedMessages(errors, KNOWN_FIELDS);
  const shownImage = image?.preview ?? null;

  return (
    <form onSubmit={(event) => void submit(event)} noValidate className="flex flex-col gap-4">
      <fieldset disabled={!canManage || save.isPending} className="flex min-w-0 flex-col gap-4 border-0 p-0">
        <Card className="flex flex-col gap-4">
          <h2 className="text-headline font-bold text-fg">{t('menu_section_main')}</h2>
          <Field label={t('menu_name')} error={errors.name}>
            {(control) => <input {...control} className={fieldClass} value={draft.name} maxLength={255} onChange={(event) => patch({ name: event.target.value })} />}
          </Field>
          <Field label={t('menu_description')} error={errors.description}>
            {(control) => <textarea {...control} className={`${fieldClass} min-h-24`} value={draft.description} onChange={(event) => patch({ description: event.target.value })} />}
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label={t('menu_category')} error={errors.category_id}>
              {(control) => (
                <select {...control} className={fieldClass} value={draft.categoryId} onChange={(event) => patch({ categoryId: event.target.value })}>
                  <option value="">{t('menu_choose_category')}</option>
                  {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                      {category.name}
                    </option>
                  ))}
                </select>
              )}
            </Field>
            <Field label={t('menu_sort_order')} hint={t('menu_sort_order_hint')} error={errors.sort_order}>
              {(control) => <input {...control} className={fieldClass} inputMode="numeric" value={draft.sortOrder} onChange={(event) => patch({ sortOrder: event.target.value })} />}
            </Field>
          </div>

          <div className="flex flex-col gap-2">
            <span className="text-footnote font-bold text-fg-muted">{t('menu_image')}</span>
            <div className="flex flex-wrap items-center gap-3">
              {shownImage ? (
                // eslint-disable-next-line @next/next/no-img-element -- a local preview (blob:) of the picked file
                <img src={shownImage} alt={t('menu_image_preview')} className="size-24 rounded-control object-cover" />
              ) : (
                <ProductThumb product={product ?? {}} className="size-24" />
              )}
              <div className="flex flex-col gap-2">
                <input
                  ref={fileInput}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  aria-label={t('menu_image_pick')}
                  className="sr-only"
                  tabIndex={-1}
                  onChange={(event) => pickImage(event.target.files?.[0])}
                />
                <div className="flex flex-wrap gap-2">
                  <Button variant="secondary" icon={ImagePlus} onClick={() => fileInput.current?.click()}>
                    {t('menu_image_pick')}
                  </Button>
                  {image && (
                    <Button variant="ghost" icon={X} onClick={clearImage}>
                      {t('menu_image_clear')}
                    </Button>
                  )}
                </div>
                <p className="text-footnote text-fg-muted">{image ? image.file.name : t('menu_image_hint')}</p>
              </div>
            </div>
            {errors.image && (
              <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
                {errors.image}
              </p>
            )}
          </div>

          <div className="flex flex-wrap gap-x-8 gap-y-2">
            <div className="flex items-center gap-2">
              <Switch checked={draft.isActive} onChange={(checked) => patch({ isActive: checked })} label={t('menu_is_active')} />
              <span className="text-body text-fg">{t('menu_is_active')}</span>
            </div>
            <div className="flex items-center gap-2">
              <Switch checked={draft.batchTracked} onChange={(checked) => patch({ batchTracked: checked })} label={t('menu_batch_tracked')} />
              <span className="text-body text-fg">
                {t('menu_batch_tracked')}
                <span className="block text-footnote text-fg-muted">{t('menu_batch_tracked_hint')}</span>
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Switch checked={draft.quickAdd} onChange={(checked) => patch({ quickAdd: checked })} label={t('menu_quick_add')} />
              <span className="text-body text-fg">
                {t('menu_quick_add')}
                <span className="block text-footnote text-fg-muted">{t('menu_quick_add_hint')}</span>
              </span>
            </div>
          </div>
        </Card>

        <ProductFiscalFields draft={draft.fiscal} onChange={(changes) => patch({ fiscal: { ...draft.fiscal, ...changes } })} errors={errors} />

        <Card className="flex flex-col gap-4">
          <h2 className="text-headline font-bold text-fg">{t('menu_section_variants')}</h2>
          <VariantFields variants={draft.variants} onChange={(variants) => patch({ variants })} batchTracked={draft.batchTracked} errors={errors} />
        </Card>
      </fieldset>

      {banner && <ErrorBanner message={banner} details={unplaced} />}
      {saved && <SuccessNote>{t('menu_saved')}</SuccessNote>}
      {canManage ? (
        <div>
          <Button type="submit" size="lg" loading={save.isPending}>
            {product ? t('menu_save') : t('menu_create')}
          </Button>
        </div>
      ) : (
        <p className="text-footnote text-fg-muted">{t('menu_read_only')}</p>
      )}
    </form>
  );
}
