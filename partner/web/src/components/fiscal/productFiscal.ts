import { isMxik, isPackageCode, type ProductFiscalInput, type ProductKind } from '@rizqim/staff';
import type { Translate } from '@/i18n';
import { parseDecimal, toInputText } from '../menu/numbers';

// The fiscal fields of the product form as the person types them. The server checks them again (and says why in Uzbek).
export interface FiscalDraft {
  kind: ProductKind;
  mxik: string;
  packageCode: string;
  vat: string;
}

interface ProductFiscalSource {
  kind?: ProductKind | null;
  mxik_code?: string | null;
  package_code?: string | null;
  vat_rate?: string | number | null;
}

export const emptyFiscalDraft = (): FiscalDraft => ({ kind: 'dish', mxik: '', packageCode: '', vat: '' });

export function fiscalDraftFrom(product: ProductFiscalSource | null | undefined): FiscalDraft {
  if (!product) return emptyFiscalDraft();
  return {
    kind: product.kind === 'service' ? 'service' : 'dish',
    mxik: product.mxik_code ?? '',
    packageCode: product.package_code ?? '',
    vat: toInputText(product.vat_rate),
  };
}

// Messages by the server's field names (mxik_code, package_code, vat_rate); a code left empty is fine here (the readiness
// list shows what is missing)
export function checkFiscalDraft(draft: FiscalDraft, t: Translate): Record<string, string> {
  const found: Record<string, string> = {};
  const mxik = draft.mxik.trim();
  if (mxik !== '' && !isMxik(mxik)) found.mxik_code = t('fiscal_err_mxik');
  const packageCode = draft.packageCode.trim();
  if (packageCode !== '' && !isPackageCode(packageCode)) found.package_code = t('fiscal_err_package');
  if (draft.vat.trim() !== '') {
    const vat = parseDecimal(draft.vat);
    if (vat === null || vat < 0 || vat > 100) found.vat_rate = t('fiscal_err_vat');
  }
  return found;
}

// Only what changed since the form was opened goes to the server: a field left out is kept there, so a product saved
// without touching these never has its codes rewritten. An emptied field is sent as null (cleared).
export function fiscalChanges(initial: FiscalDraft, draft: FiscalDraft): ProductFiscalInput {
  const changes: ProductFiscalInput = {};
  if (draft.kind !== initial.kind) changes.kind = draft.kind;
  if (draft.mxik.trim() !== initial.mxik.trim()) changes.mxik_code = draft.mxik.trim() || null;
  if (draft.packageCode.trim() !== initial.packageCode.trim()) changes.package_code = draft.packageCode.trim() || null;
  if (draft.vat.trim() !== initial.vat.trim()) changes.vat_rate = parseDecimal(draft.vat);
  return changes;
}
