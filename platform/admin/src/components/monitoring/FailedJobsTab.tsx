'use client';

import { useState, type FormEvent } from 'react';
import { ChevronRight, ListChecks, RotateCw, Trash2 } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, fieldClass, InlineError, Notice, Pill } from '@rizqim/ui';
import { ApiError } from '@/lib/api/client';
import { EMPTY_JOB_FILTERS, useDeleteJob, useFailedJob, useFailedJobs, useRetryJob, type FailedJobFilters, type FailedJobRow } from '@/lib/api/monitoring';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { NoteDialog } from './NoteDialog';
import { monitoringError } from './words';

// Yiqilgan vazifalar: the background jobs that gave up. The list shows the job, its queue and the first line of the error; a row
// opens the detail with the whole (redacted) error and the job's data. Retrying needs monitoring.manage, deleting also a fresh 2FA
// code (the shell asks for it) and a reason.
export function FailedJobsTab() {
  const t = useT();
  const [draft, setDraft] = useState<FailedJobFilters>(EMPTY_JOB_FILTERS);
  const [applied, setApplied] = useState<FailedJobFilters>(EMPTY_JOB_FILTERS);
  const jobs = useFailedJobs(applied);
  const [openUuid, setOpenUuid] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const rows = jobs.data?.pages.flatMap((page) => page.data) ?? [];
  const total = jobs.data?.pages[0]?.total ?? 0;

  function apply(event: FormEvent) {
    event.preventDefault();
    setApplied({ q: draft.q.trim(), queue: draft.queue.trim() });
  }

  return (
    <div className="flex flex-col gap-4">
      <Notice message={notice} />
      <form onSubmit={apply} aria-label={t('monitoring_jobs_filtersLabel')} className="flex max-w-4xl flex-col gap-3 rounded-card border border-line bg-surface p-4">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label={t('monitoring_jobs_q')}>
            {(control) => <input {...control} type="search" value={draft.q} placeholder={t('monitoring_jobs_qPlaceholder')} autoCapitalize="none" onChange={(event) => setDraft((previous) => ({ ...previous, q: event.target.value }))} className={fieldClass} />}
          </Field>
          <Field label={t('monitoring_jobs_queue')}>
            {(control) => <input {...control} type="text" value={draft.queue} placeholder={t('monitoring_jobs_queuePlaceholder')} autoCapitalize="none" onChange={(event) => setDraft((previous) => ({ ...previous, queue: event.target.value }))} className={fieldClass} />}
          </Field>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="submit">{t('monitoring_jobs_apply')}</Button>
          <Button
            variant="secondary"
            onClick={() => {
              setDraft(EMPTY_JOB_FILTERS);
              setApplied(EMPTY_JOB_FILTERS);
            }}
          >
            {t('monitoring_jobs_clear')}
          </Button>
        </div>
      </form>

      {jobs.isError ? (
        <InlineError title={t('monitoring_jobs_loadError')} message={errorText(jobs.error, t)} retryLabel={t('retry')} retrying={jobs.isFetching} onRetry={() => void jobs.refetch()} />
      ) : !jobs.isSuccess ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : rows.length === 0 ? (
        <EmptyState icon={ListChecks} title={t('monitoring_jobs_empty')} text={t('monitoring_jobs_emptyText')} />
      ) : (
        <div className="flex max-w-4xl flex-col gap-3">
          <p className="text-footnote font-bold text-fg-muted">{t('monitoring_jobs_total', { total })}</p>
          <ul aria-label={t('monitoring_jobs_listLabel')} className="flex flex-col gap-2">
            {rows.map((job) => (
              <JobRow key={job.uuid} job={job} onOpen={() => setOpenUuid(job.uuid)} />
            ))}
          </ul>
          {jobs.hasNextPage && (
            <Button variant="secondary" loading={jobs.isFetchingNextPage} onClick={() => void jobs.fetchNextPage()}>
              {t('monitoring_loadMore')}
            </Button>
          )}
        </div>
      )}

      {openUuid && (
        <JobDetail
          uuid={openUuid}
          onClose={() => setOpenUuid(null)}
          onDone={(message) => {
            setOpenUuid(null);
            setNotice(message);
          }}
        />
      )}
    </div>
  );
}

function JobRow({ job, onOpen }: { job: FailedJobRow; onOpen: () => void }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <button
          type="button"
          aria-label={t('monitoring_jobs_open', { job: job.job, time: when(job.failed_at) })}
          onClick={onOpen}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="flex flex-wrap items-center gap-2">
              <span className="break-all text-body font-bold text-fg">{job.job}</span>
              <Pill>{job.queue}</Pill>
            </span>
            <span className="text-footnote text-fg-muted">{when(job.failed_at)}</span>
            <span className="line-clamp-2 break-words font-mono text-caption text-fg-muted">{job.exception_line}</span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </li>
  );
}

function JobDetail({ uuid, onClose, onDone }: { uuid: string; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const { can } = useCan();
  const manage = can('monitoring.manage');
  const job = useFailedJob(uuid);
  const retry = useRetryJob();
  const remove = useDeleteJob();
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const data = job.data;
  const gone = job.error instanceof ApiError && job.error.status === 404;
  const info: Array<[string, string]> = data
    ? [
        [t('monitoring_jobs_job'), data.job_class],
        [t('monitoring_jobs_queueLabel'), data.queue],
        [t('monitoring_jobs_connection'), data.connection],
        [t('monitoring_jobs_failedAt'), when(data.failed_at)],
        ...(data.attempts !== null ? ([[t('monitoring_jobs_attempts'), t('monitoring_jobs_attemptsValue', { n: data.attempts, max: data.max_tries ?? '—' })]] as Array<[string, string]>) : []),
        [t('monitoring_jobs_id'), data.uuid],
      ]
    : [];

  return (
    <>
      <Dialog
        open
        title={t('monitoring_jobs_detailTitle')}
        closeLabel={t('close')}
        onClose={onClose}
        busy={retry.isPending}
        footer={
          data && manage ? (
            <>
              <Button variant="secondary" icon={RotateCw} loading={retry.isPending} onClick={() => retryNow()}>
                {t('monitoring_jobs_retry')}
              </Button>
              <Button variant="danger" icon={Trash2} disabled={retry.isPending} onClick={() => setConfirmDelete(true)}>
                {t('monitoring_jobs_delete')}
              </Button>
            </>
          ) : (
            <Button variant="secondary" onClick={onClose}>
              {t('close')}
            </Button>
          )
        }
      >
        {job.isError ? (
          gone ? (
            <p role="alert" className="rounded-control bg-warning-soft px-3 py-2.5 text-footnote font-bold text-warning-soft-fg">
              {t('monitoring_jobs_gone')}
            </p>
          ) : (
            <InlineError title={t('monitoring_jobs_detailLoadError')} message={errorText(job.error, t)} retryLabel={t('retry')} retrying={job.isFetching} onRetry={() => void job.refetch()} />
          )
        ) : !data ? (
          <CardSkeletons count={2} lines={2} label={t('loading')} />
        ) : (
          <>
            <dl className="flex flex-col gap-2">
              {info.map(([name, value]) => (
                <div key={name} className="flex flex-col">
                  <dt className="text-caption font-bold text-fg-muted">{name}</dt>
                  <dd className="break-all text-footnote text-fg">{value}</dd>
                </div>
              ))}
            </dl>
            <section aria-label={t('monitoring_jobs_exception')} className="flex flex-col gap-1">
              <h3 className="text-footnote font-bold text-fg-muted">{t('monitoring_jobs_exception')}</h3>
              <pre className="max-h-64 overflow-auto whitespace-pre-wrap break-all rounded-control bg-surface-2 p-3 font-mono text-caption text-fg">{data.exception}</pre>
            </section>
            <section aria-label={t('monitoring_jobs_payload')} className="flex flex-col gap-1">
              <h3 className="text-footnote font-bold text-fg-muted">{t('monitoring_jobs_payload')}</h3>
              <pre className="max-h-64 overflow-auto whitespace-pre-wrap break-all rounded-control bg-surface-2 p-3 font-mono text-caption text-fg">{JSON.stringify(data.payload, null, 2)}</pre>
              <p className="text-caption text-fg-muted">{t('monitoring_jobs_redacted')}</p>
            </section>
            {error && !confirmDelete && (
              <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-footnote font-bold text-danger-soft-fg">
                {error}
              </p>
            )}
          </>
        )}
      </Dialog>

      {confirmDelete && (
        <NoteDialog
          title={t('monitoring_jobs_deleteTitle')}
          text={t('monitoring_jobs_deleteText')}
          label={t('monitoring_reason_label')}
          placeholder={t('monitoring_reason_placeholder')}
          confirmLabel={t('monitoring_jobs_deleteConfirm')}
          required
          minLength={3}
          tone="danger"
          busy={remove.isPending}
          error={error}
          onClose={() => setConfirmDelete(false)}
          onSubmit={(reason) => {
            setError(null);
            remove.mutate(
              { uuid, reason },
              {
                onSuccess: () => onDone(t('monitoring_jobs_deleteDone')),
                onError: (failure) => {
                  setError(monitoringError(failure, t, 'monitoring_jobs_gone'));
                },
              },
            );
          }}
        />
      )}
    </>
  );

  function retryNow() {
    setError(null);
    retry.mutate(uuid, {
      onSuccess: () => onDone(t('monitoring_jobs_retryDone')),
      onError: (failure) => setError(monitoringError(failure, t, 'monitoring_jobs_gone')),
    });
  }
}
