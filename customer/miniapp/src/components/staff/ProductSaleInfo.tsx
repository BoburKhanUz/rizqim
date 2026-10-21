import { ExternalLink, Layers, Scale } from 'lucide-react';
import { formatQuantity, isMeasuredUnit, type Localized, type MenuUnit, type ModifierGroup, type ProductVariant } from '@rizqim/staff';
import { useLanguageStore } from '../../store/languageStore';
import { toAmount } from '../../lib/money';
import { formatMoney } from '../../lib/format';
import { openExternalLink } from '../../lib/openMap';
import { useStaffText } from '../../hooks/useStaffText';
import { Card, Section } from '../ui';
import { Pill } from './Pill';

const TEXT = {
  unit: { uz: 'Sotuv birligi', ru: 'Единица продажи' },
  piece: { uz: 'Dona', ru: 'Штуки' },
  kg: { uz: 'Kilogramm', ru: 'Килограммы' },
  l: { uz: 'Litr', ru: 'Литры' },
  step: { uz: 'Qadam: {{value}}', ru: 'Шаг: {{value}}' },
  min: { uz: 'Eng kami: {{value}}', ru: 'Минимум: {{value}}' },
  consumption: { uz: 'Qozondan: {{value}} porsiya', ru: 'Из котла: {{value}} порц.' },
  batchTracked: { uz: "Qozon bo'yicha sotiladi", ru: 'Продаётся из котла' },
  groups: { uz: 'Modifikator guruhlari', ru: 'Группы модификаторов' },
  noGroups: { uz: "Bu taomga modifikator ulanmagan", ru: 'К блюду не подключены модификаторы' },
  required: { uz: 'Majburiy', ru: 'Обязательно' },
  optional: { uz: 'Ixtiyoriy', ru: 'По желанию' },
  pickRange: { uz: '{{min}}–{{max}} ta', ru: '{{min}}–{{max}} шт.' },
  pickUpTo: { uz: "ko'pi bilan {{max}} ta", ru: 'не более {{max}}' },
  inactive: { uz: 'Faol emas', ru: 'Неактивно' },
  editOnWeb: { uz: 'Web panelda tahrirlash', ru: 'Редактировать в веб-панели' },
  editOnWebHint: { uz: 'Vazn, modifikatorlar va kanal narxlari faqat web panelda tahrirlanadi.', ru: 'Вес, модификаторы и цены по каналам редактируются только в веб-панели.' },
  readOnly: { uz: "Faqat ko'rish", ru: 'Только просмотр' },
} satisfies Record<string, Localized>;

// "Web panelda tahrirlash": where the full editor of a dish lives. A link when the panel's address is configured
// (VITE_PARTNER_WEB_URL), the words alone otherwise.
export function WebEditHint() {
  const tx = useStaffText(TEXT);
  const base = import.meta.env.VITE_PARTNER_WEB_URL?.trim();
  return (
    <div className="flex flex-col gap-1 rounded-card border border-line bg-surface-2 p-3">
      {base ? (
        <button
          type="button"
          onClick={() => openExternalLink(`${base.replace(/\/+$/, '')}/menu`)}
          className="flex min-h-11 cursor-pointer items-center gap-1.5 text-left text-body font-bold text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          {tx('editOnWeb')}
          <ExternalLink aria-hidden="true" className="size-4" />
        </button>
      ) : (
        <p className="text-body font-bold text-brand">{tx('editOnWeb')}</p>
      )}
      <p className="text-footnote text-fg-muted">{tx('editOnWebHint')}</p>
    </div>
  );
}

// What a variant is sold in, read only: the unit, and for a weighed one the step and the smallest amount ("1,5 kg").
export function VariantSaleInfo({ variant, batchTracked }: { variant: Pick<ProductVariant, 'unit' | 'quantity_step' | 'min_quantity' | 'batch_consumption'>; batchTracked: boolean }) {
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(TEXT);
  const unit = (variant.unit ?? 'piece') as MenuUnit;
  const measured = isMeasuredUnit(unit);
  const step = variant.quantity_step === null ? null : Number(variant.quantity_step);
  const min = variant.min_quantity === null ? null : Number(variant.min_quantity);
  const consumption = Number(variant.batch_consumption);
  return (
    <div aria-label={tx('readOnly')} className="flex flex-wrap items-center gap-1.5 rounded-control bg-surface-2 px-3 py-2 text-footnote text-fg-muted">
      <Scale aria-hidden="true" className="size-4 shrink-0 text-brand" />
      <span>
        {tx('unit')}: <span className="font-bold text-fg">{tx(unit)}</span>
      </span>
      {measured && step !== null && step > 0 && <Pill>{tx('step', { value: formatQuantity(step, unit, language) })}</Pill>}
      {measured && min !== null && min > 0 && <Pill>{tx('min', { value: formatQuantity(min, unit, language) })}</Pill>}
      {batchTracked && Number.isFinite(consumption) && consumption !== 1 && <Pill tone="info">{tx('consumption', { value: String(consumption).replace('.', ',') })}</Pill>}
    </div>
  );
}

// The modifier groups of a dish and their modifiers, read only. Each group's own rules (the dish may override them on the
// web panel; the phone shows the group's).
export function ModifierGroupsInfo({ groups }: { groups: readonly ModifierGroup[] }) {
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(TEXT);
  return (
    <Section title={tx('groups')}>
      {groups.length === 0 ? (
        <Card className="flex items-center gap-2 text-footnote text-fg-muted">
          <Layers aria-hidden="true" className="size-4 shrink-0" />
          {tx('noGroups')}
        </Card>
      ) : (
        <ul className="flex flex-col gap-3">
          {groups.map((group) => (
            <li key={group.id}>
              <Card className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="text-body font-bold text-fg">{group.name}</h3>
                  <Pill tone={group.is_required ? 'warning' : 'neutral'}>{group.is_required ? tx('required') : tx('optional')}</Pill>
                  {!group.is_active && <Pill tone="danger">{tx('inactive')}</Pill>}
                  <span className="text-footnote text-fg-muted">
                    {group.max_select === null ? '' : group.min_select > 0 ? tx('pickRange', { min: group.min_select, max: group.max_select }) : tx('pickUpTo', { max: group.max_select })}
                  </span>
                </div>
                <ul className="flex flex-col gap-1 text-body text-fg">
                  {group.modifiers.map((modifier) => (
                    <li key={modifier.id} className="flex items-baseline justify-between gap-3">
                      <span className={modifier.is_active ? '' : 'text-fg-muted line-through'}>{modifier.name}</span>
                      <span className="shrink-0 text-footnote text-fg-muted">
                        {toAmount(modifier.price_delta) > 0 ? `+${formatMoney(toAmount(modifier.price_delta), language)}` : ''}
                      </span>
                    </li>
                  ))}
                </ul>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </Section>
  );
}
