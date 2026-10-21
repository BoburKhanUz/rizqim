'use client';

import Link from 'next/link';
import { ArrowRight, TriangleAlert } from 'lucide-react';
import { buttonClasses, Card, cx, formatNumber, InlineError, Section, Skeleton } from '@rizqim/ui';
import { useStaffOrders } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useBranchScope } from '../shell/BranchScope';
import { useLiveOrders } from '../shell/LiveOrders';
import { BOARD_COLUMNS, groupBoard, waitingOf } from './boardModel';
import { useNow } from './useNow';

const OPEN_COLUMNS = BOARD_COLUMNS.filter((column) => column.id !== 'done');

// The dashboard's "Now": how many open orders sit in each board column of the chosen branch scope, and how many new
// ones have waited too long, with a link to the board. Same data and freshness as the board (GET /staff/orders).
export function BoardSummary() {
  const { t, language } = useI18n();
  const { branchId } = useBranchScope();
  const { pollInterval } = useLiveOrders();
  const now = useNow(30_000);
  const active = useStaffOrders({ scope: 'active', ...(branchId === null ? {} : { branch_id: branchId }) }, { refetchInterval: pollInterval });
  const groups = groupBoard(active.data?.orders ?? [], []);
  const late = groups.new.filter((order) => waitingOf(order, now)?.tone === 'late').length;

  return (
    <Section
      title={t('board_summaryTitle')}
      action={
        <Link href="/orders" className={buttonClasses({ variant: 'ghost' })}>
          {t('board_summaryOpen')}
          <ArrowRight aria-hidden="true" className="size-[18px]" />
        </Link>
      }
    >
      {active.isError && !active.data ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={active.isFetching} onRetry={() => void active.refetch()} />
      ) : (
        <div className="flex flex-col gap-3">
          <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
            {OPEN_COLUMNS.map((column) => {
              const count = groups[column.id].length;
              return (
                <Card key={column.id} className="flex flex-col gap-1" data-column={column.id}>
                  <p className="text-footnote font-bold text-fg-muted">{t(column.label)}</p>
                  {active.isPending ? (
                    <Skeleton className="h-9 w-12" />
                  ) : (
                    <p className={cx('text-display font-extrabold', column.id === 'new' && count > 0 ? 'text-warning' : 'text-fg')}>{formatNumber(count, language)}</p>
                  )}
                </Card>
              );
            })}
          </div>
          {late > 0 && (
            <p role="status" className="inline-flex items-center gap-2 rounded-control bg-danger-soft px-3 py-2 text-body font-medium text-danger-soft-fg">
              <TriangleAlert aria-hidden="true" className="size-5" />
              {t('board_summaryWaitingLong', { n: late })}
            </p>
          )}
        </div>
      )}
    </Section>
  );
}
