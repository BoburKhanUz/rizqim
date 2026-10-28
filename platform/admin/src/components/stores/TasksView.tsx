'use client';

import { useState } from 'react';
import { ListChecks, ShieldAlert } from 'lucide-react';
import { Card, CardSkeletons, cx, EmptyState, InlineError, PageHeader } from '@rizqim/ui';
import { useTasks } from '@/lib/api/stores';
import { errorText } from '@/lib/errors';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { TaskList } from './CrmPanel';
import { StoresNav } from './StoresNav';

// Vazifalar (P4-22, CRM-lite): the team's calls and reminders across stores and applications, the overdue first. Mine or
// everyone's; due today and earlier, or all open ones. The same tasks show in Bugun from their due day.

const CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
const chipTone = (on: boolean) => (on ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-fg hover:bg-surface-2');

export function TasksView() {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('stores.view');
  const [mine, setMine] = useState(true);
  const [dueOnly, setDueOnly] = useState(false);
  const tasks = useTasks({ assignee_id: mine ? 'me' : undefined, due: dueOnly ? 'today' : undefined }, allowed);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_stores')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  return (
    <>
      <PageHeader title={t('stores_tasksTitle')} subtitle={t('stores_tasksSubtitle')} />
      <StoresNav current="tasks" />
      <div className="mb-4 flex max-w-5xl flex-wrap gap-2">
        <button type="button" aria-pressed={mine} className={cx(CHIP, chipTone(mine))} onClick={() => setMine(true)}>
          {t('stores_tasksMine')}
        </button>
        <button type="button" aria-pressed={!mine} className={cx(CHIP, chipTone(!mine))} onClick={() => setMine(false)}>
          {t('stores_tasksAll')}
        </button>
        <button type="button" aria-pressed={dueOnly} className={cx(CHIP, chipTone(dueOnly))} onClick={() => setDueOnly((value) => !value)}>
          {t('stores_tasksDueToday')}
        </button>
      </div>
      {tasks.isError ? (
        <InlineError title={t('stores_tasksLoadError')} message={errorText(tasks.error, t)} retryLabel={t('retry')} retrying={tasks.isFetching} onRetry={() => void tasks.refetch()} />
      ) : !tasks.data ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : (
        <div className="flex max-w-5xl flex-col gap-3">
          <p className="text-footnote text-fg-muted">{t('stores_tasksCounts', tasks.data.counts)}</p>
          {tasks.data.tasks.length === 0 ? (
            <EmptyState icon={ListChecks} title={t('stores_tasksEmpty')} />
          ) : (
            <Card>
              <TaskList tasks={tasks.data.tasks} canManage={can('stores.manage')} showSubject />
            </Card>
          )}
        </div>
      )}
    </>
  );
}
