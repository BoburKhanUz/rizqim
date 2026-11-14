'use client';

import Link from 'next/link';
import { ChefHat, Calculator, FileSignature } from 'lucide-react';
import { Card } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';

// P6-54 on the event page (components/banquets/sections.tsx): the kitchen's production sheet, the contract and the
// final settlement of this event, each on its own page (printable), as far as the reader may open them.
export function BanquetOpsSection({ eventUuid }: { eventUuid: string }) {
  const { t } = useI18n();
  const access = useCan();
  const links = [
    { href: `/banquets/production?event=${eventUuid}`, label: t('bqo_navProduction'), icon: ChefHat, show: true },
    { href: `/banquets/contract?event=${eventUuid}`, label: t('bqo_navContract'), icon: FileSignature, show: access.can('banquets.manage') },
    { href: `/banquets/settlement?event=${eventUuid}`, label: t('bqo_navSettlement'), icon: Calculator, show: true },
  ].filter((link) => link.show);

  return (
    <Card className="flex flex-wrap gap-2">
      {links.map(({ href, label, icon: Icon }) => (
        <Link key={href} href={href} className="inline-flex items-center gap-2 rounded-control border border-line px-3 py-2 font-bold text-brand hover:bg-line">
          <Icon className="size-4" aria-hidden="true" />
          {label}
        </Link>
      ))}
    </Card>
  );
}
