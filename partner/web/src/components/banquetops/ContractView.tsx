'use client';

import { useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { FileSignature, Hash, Printer } from 'lucide-react';
import { Button, Card, EmptyState, FormError, InlineError, Notice, PageHeader, Skeleton } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { EVENT_UUID, EventOpsNav, NoEvent, Warnings } from './BanquetOpsNav';
import { useContract, useNumberContract } from './hooks';

const newKey = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);

// /banquets/contract?event=<uuid> (P6-54, E5): the event's contract (shartnoma) filled from the store's template — the
// print view of the panel (the till prints the same text on paper) — and its number, given once (banquets.manage: the
// contract carries the customer's name and phone).
export function ContractView() {
  const { t } = useI18n();
  const uuid = useSearchParams().get('event') ?? '';

  return (
    <>
      <div className="print:hidden">
        <PageHeader title={t('bqo_contractTitle')} />
      </div>
      {EVENT_UUID.test(uuid) ? <Contract uuid={uuid} /> : <NoEvent />}
    </>
  );
}

function Contract({ uuid }: { uuid: string }) {
  const { t } = useI18n();
  const access = useCan();
  const contract = useContract(uuid);
  const [notice, setNotice] = useState<string | null>(null);
  const [key, setKey] = useState(newKey);
  const number = useNumberContract(uuid, {
    onSuccess: (document) => {
      setNotice(t('bqo_numbered', { no: document.contract_no ?? '' }));
      setKey(newKey());
    },
  });

  if (access.ready && !access.can('banquets.manage')) return <EmptyState icon={FileSignature} title={t('mgmt_forbidden')} text={t('bqo_noAccess')} />;
  if (contract.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(contract.error, t)} retryLabel={t('retry')} retrying={contract.isFetching} onRetry={() => void contract.refetch()} />;
  const document = contract.data;
  if (!document) return <Skeleton className="h-96 w-full" />;

  return (
    <>
      <EventOpsNav uuid={uuid} current="contract" />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <span className="text-headline font-extrabold text-fg">{document.contract_no ? t('bqo_contractNo', { no: document.contract_no }) : t('bqo_contractNoNone')}</span>
        <div className="flex gap-2">
          {!document.contract_no && (
            <Button variant="secondary" icon={Hash} loading={number.isPending} onClick={() => number.mutate(key)}>
              {t('bqo_assignNumber')}
            </Button>
          )}
          <Button icon={Printer} onClick={() => window.print()}>
            {t('bqo_print')}
          </Button>
        </div>
      </div>
      <Notice message={notice} />
      {number.isError && <FormError message={errorText(number.error, t)} />}
      <div className="print:hidden">
        <Warnings warnings={document.warnings} />
      </div>
      <Card className="print:border-0 print:p-0 print:shadow-none">
        <pre data-testid="contract-text" className="whitespace-pre-wrap font-sans text-body leading-relaxed text-fg">
          {document.text}
        </pre>
      </Card>
    </>
  );
}
