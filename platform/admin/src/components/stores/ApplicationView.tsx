'use client';

import Link from 'next/link';
import { useState } from 'react';
import { ArrowLeft, ArrowRight, Check, CircleCheck, Copy, ExternalLink, RotateCcw, ShieldAlert, Store, UserCheck, Workflow, XCircle } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, fieldClass, FormError, InlineError, Notice, PageHeader, Pill, Section } from '@rizqim/ui';
import { ApiError } from '@/lib/api/client';
import { copyText } from '@/lib/clipboard';
import { PIPELINE_STAGES, useApplication, useConvert, useDistricts, useMoveApplication, useReject, useTick, useUpdateApplication, type ApplicationCard } from '@/lib/api/stores';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan, useMe } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { CrmPanel } from './CrmPanel';
import { ReasonForm } from './StoreDialogs';
import { RegionSelect } from './StorePicker';
import { day, SOURCE_LABEL } from './storesModel';

type Dialog = { kind: 'convert' } | { kind: 'reject' } | { kind: 'activate' } | { kind: 'link'; link: string; storeId: number };

// One application of the pipeline (/stores/applications/[uuid], P4-22): its details, the checklist of every stage (ticked
// by hand or detected from the store), the move to the next or previous stage, creating the store (the same path as the
// Filament wizard), rejection and the CRM. Converting, rejecting and the move to `faol` ask for a reason; the shell asks
// for the step-up.
export function ApplicationView({ uuid }: { uuid: string }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('stores.view');
  const card = useApplication(uuid, allowed);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_stores')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const back = (
    <Link href="/stores/applications" className="mb-3 inline-flex min-h-11 items-center gap-1.5 text-footnote font-bold text-brand hover:underline">
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('stores_applicationBack')}
    </Link>
  );

  if (card.isError) {
    const missing = card.error instanceof ApiError && card.error.status === 404;
    return (
      <>
        {back}
        {missing ? (
          <EmptyState icon={Workflow} title={t('stores_applicationNotFound')} />
        ) : (
          <InlineError title={t('stores_applicationLoadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />
        )}
      </>
    );
  }
  if (!card.isSuccess) {
    return (
      <>
        {back}
        <CardSkeletons count={3} lines={4} label={t('loading')} />
      </>
    );
  }

  const application = card.data;
  const manage = application.actions.manage;

  return (
    <>
      {back}
      <PageHeader
        title={t('stores_applicationTitle', { name: application.name })}
        subtitle={t('stores_applicationFrom', { source: t(SOURCE_LABEL[application.source] ?? 'stores_source_admin'), time: when(application.created_at) })}
      />
      <Notice message={notice} />

      <div className="flex max-w-5xl flex-col gap-5">
        <Card className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <Pill tone={application.stage === 'rejected' ? 'danger' : application.stage === 'active' ? 'success' : 'info'}>{application.stage_label}</Pill>
            {application.overdue && <Pill tone="danger">{t('stores_overdue')}</Pill>}
            {application.due_on && <span className="text-footnote text-fg-muted">{t('stores_due', { date: day(application.due_on) })}</span>}
            {application.store && (
              <Link href={`/stores/${application.store.id}`} className="inline-flex min-h-11 items-center gap-1 text-footnote font-bold text-brand hover:underline">
                {t('stores_openStore')}
                <ExternalLink aria-hidden="true" className="size-3.5" />
              </Link>
            )}
          </div>
          {application.rejected_reason && <p className="break-words text-footnote text-danger-soft-fg">{t('stores_rejected', { reason: application.rejected_reason })}</p>}
          {manage && <StageButtons application={application} onNotice={setNotice} onActivate={() => setDialog({ kind: 'activate' })} />}
          {manage && (application.actions.convert || application.actions.reject) && (
            <div role="group" aria-label={t('stores_actions')} className="flex flex-wrap gap-2">
              {application.actions.convert && (
                <Button icon={Store} onClick={() => setDialog({ kind: 'convert' })}>
                  {t('stores_convert')}
                </Button>
              )}
              {application.actions.reject && (
                <Button variant="danger" icon={XCircle} onClick={() => setDialog({ kind: 'reject' })}>
                  {t('stores_reject')}
                </Button>
              )}
            </div>
          )}
        </Card>

        <Details application={application} manage={manage} onNotice={setNotice} />

        <Section title={t('stores_checklist')}>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            {application.checklist.map((stage) => (
              <Checklist key={stage.stage} application={application} stage={stage} manage={manage} />
            ))}
          </div>
        </Section>

        <Section title={t('stores_crm')}>
          <CrmPanel crm={application.crm} applicationUuid={application.uuid} canManage={manage} onNotice={setNotice} />
        </Section>

        <Section title={t('stores_audit')}>
          <Card padded={false}>
            {application.audit.length === 0 ? (
              <p className="p-4 text-footnote text-fg-muted">{t('stores_auditEmpty')}</p>
            ) : (
              <ul aria-label={t('stores_audit')} className="divide-y divide-line">
                {application.audit.map((entry) => (
                  <li key={entry.id} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                    <span className="font-mono text-fg">{entry.action}</span>
                    {entry.reason && <span className="break-words text-fg">{entry.reason}</span>}
                    <span className="text-fg-muted">
                      {entry.actor?.name ?? t('stores_auditSystem')} · {when(entry.created_at)}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </Section>
      </div>

      {dialog?.kind === 'convert' && (
        <ConvertDialog
          application={application}
          onClose={() => setDialog(null)}
          onDone={(link, storeId) => {
            setNotice(t('stores_convertDone'));
            setDialog({ kind: 'link', link, storeId });
          }}
        />
      )}
      {dialog?.kind === 'link' && <InviteLinkDialog link={dialog.link} storeId={dialog.storeId} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'reject' && (
        <RejectDialog
          application={application}
          onClose={() => setDialog(null)}
          onDone={() => {
            setDialog(null);
            setNotice(t('stores_rejectDone'));
          }}
        />
      )}
      {dialog?.kind === 'activate' && (
        <ActivateDialog
          application={application}
          onClose={() => setDialog(null)}
          onDone={(message) => {
            setDialog(null);
            setNotice(message);
          }}
        />
      )}
    </>
  );
}

function StageButtons({ application, onNotice, onActivate }: { application: ApplicationCard; onNotice: (message: string) => void; onActivate: () => void }) {
  const t = useT();
  const move = useMoveApplication(application.uuid);
  const stages = PIPELINE_STAGES as readonly string[];
  const labelOf = (key: string) => application.checklist.find((stage) => stage.stage === key)?.label ?? (key === 'active' ? 'Faol' : key);
  const index = stages.indexOf(application.stage);

  if (application.stage === 'active') return null;
  if (application.stage === 'rejected') {
    return (
      <div className="flex flex-col gap-2">
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" icon={RotateCcw} loading={move.isPending} onClick={() => move.mutate({ stage: 'application' }, { onSuccess: (card) => onNotice(t('stores_stageMoved', { label: card.stage_label })) })}>
            {t('stores_reopen')}
          </Button>
        </div>
        <FormError message={move.error ? errorText(move.error, t) : null} />
      </div>
    );
  }

  const next = stages[index + 1];
  const previous = index > 0 ? stages[index - 1] : undefined;
  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap gap-2">
        {next && (
          <Button
            icon={next === 'active' ? CircleCheck : ArrowRight}
            loading={move.isPending}
            onClick={() => (next === 'active' ? onActivate() : move.mutate({ stage: next }, { onSuccess: (card) => onNotice(t('stores_stageMoved', { label: card.stage_label })) }))}
          >
            {t('stores_stageMove', { label: labelOf(next) })}
          </Button>
        )}
        {previous && (
          <Button variant="ghost" icon={ArrowLeft} disabled={move.isPending} onClick={() => move.mutate({ stage: previous }, { onSuccess: (card) => onNotice(t('stores_stageMoved', { label: card.stage_label })) })}>
            {t('stores_stageBack', { label: labelOf(previous) })}
          </Button>
        )}
      </div>
      <FormError message={move.error ? errorText(move.error, t) : null} />
    </div>
  );
}

function Details({ application, manage, onNotice }: { application: ApplicationCard; manage: boolean; onNotice: (message: string) => void }) {
  const t = useT();
  const me = useMe();
  const update = useUpdateApplication(application.uuid);
  const [due, setDue] = useState(application.due_on ?? '');
  const myId = me?.member.id ?? null;
  return (
    <Section title={t('stores_details')}>
      <Card className="flex flex-col gap-3">
        <dl className="grid grid-cols-1 gap-x-4 gap-y-2 text-footnote sm:grid-cols-[auto_1fr]">
          {(
            [
              [t('stores_fieldFormat'), application.format_label],
              [t('stores_fieldOwner'), application.owner_name],
              [t('stores_fieldPhone'), application.phone ?? '—'],
              [t('stores_region'), [application.region?.name, application.city].filter(Boolean).join(', ') || '—'],
              [t('stores_tin'), application.tin ?? '—'],
              [t('stores_comment'), application.comment ?? '—'],
              [t('stores_assignee'), application.assignee?.name ?? t('stores_noAssignee')],
            ] as Array<[string, string]>
          ).map(([label, value]) => (
            <div key={label} className="contents">
              <dt className="text-fg-muted">{label}</dt>
              <dd className="whitespace-pre-wrap break-words text-fg">{value}</dd>
            </div>
          ))}
        </dl>
        {manage && (
          <div className="flex flex-wrap items-end gap-2 border-t border-line pt-3">
            {myId !== null && application.assignee?.id !== myId && (
              <Button variant="secondary" icon={UserCheck} loading={update.isPending && 'assignee_id' in (update.variables ?? {})} onClick={() => update.mutate({ assignee_id: myId })}>
                {t('stores_assigneeMe')}
              </Button>
            )}
            {application.stage !== 'active' && application.stage !== 'rejected' && (
              <form
                className="flex flex-wrap items-end gap-2"
                onSubmit={(event) => {
                  event.preventDefault();
                  update.mutate({ due_on: due || null }, { onSuccess: () => onNotice(t('stores_dueSaved')) });
                }}
              >
                <Field label={t('stores_dueOn')}>{(control) => <input {...control} type="date" className={fieldClass} value={due} onChange={(event) => setDue(event.target.value)} />}</Field>
                <Button type="submit" variant="secondary" disabled={(due || null) === application.due_on}>
                  {t('stores_saveDue')}
                </Button>
              </form>
            )}
          </div>
        )}
        <FormError message={update.error ? errorText(update.error, t) : null} />
      </Card>
    </Section>
  );
}

function Checklist({ application, stage, manage }: { application: ApplicationCard; stage: ApplicationCard['checklist'][number]; manage: boolean }) {
  const t = useT();
  const tick = useTick(application.uuid);
  return (
    <Card className="flex flex-col gap-2">
      <h3 className="flex flex-wrap items-center gap-2 text-footnote font-bold text-fg">
        {stage.label}
        {stage.current && <Pill tone="info">{application.stage_label}</Pill>}
        {stage.passed && <Check aria-hidden="true" className="size-4 text-success" />}
      </h3>
      <ul aria-label={stage.label} className="flex flex-col gap-1">
        {stage.items.map((item) => (
          <li key={item.key}>
            <label className="flex min-h-11 cursor-pointer items-center gap-3 text-footnote text-fg">
              <input
                type="checkbox"
                className="size-5 accent-[var(--color-brand)]"
                checked={item.done}
                disabled={!manage || item.detected || tick.isPending}
                onChange={(event) => tick.mutate({ item: item.key, done: event.target.checked })}
              />
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="break-words">{item.label}</span>
                <span className="text-caption text-fg-muted">{[item.required ? t('stores_checklistRequired') : null, item.detected ? t('stores_checklistDetected') : null].filter(Boolean).join(' · ')}</span>
              </span>
            </label>
          </li>
        ))}
      </ul>
      <FormError message={tick.error ? errorText(tick.error, t) : null} />
    </Card>
  );
}

function ConvertDialog({ application, onClose, onDone }: { application: ApplicationCard; onClose: () => void; onDone: (link: string, storeId: number) => void }) {
  const t = useT();
  const convert = useConvert(application.uuid);
  const [region, setRegion] = useState(application.region ? String(application.region.id) : '');
  const districts = useDistricts(region ? Number(region) : null);
  const [form, setForm] = useState({ district: '', address: '', lat: '', lng: '', radius: '5', owner_phone: '' });
  const set = (name: keyof typeof form, value: string) => setForm((previous) => ({ ...previous, [name]: value }));
  const coordinate = (value: string) => /^-?\d{1,3}(\.\d+)?$/.test(value.trim());
  const ready = region !== '' && form.district !== '' && form.address.trim() !== '' && coordinate(form.lat) && coordinate(form.lng);
  const error = (field: string) => fieldError(convert.error, field);
  return (
    <ReasonForm
      title={t('stores_convertTitle')}
      text={t('stores_convertText')}
      submitLabel={t('stores_convert')}
      busy={convert.isPending}
      error={convert.error && !Object.keys((convert.error as ApiError).fields ?? {}).some((key) => key !== 'reason') ? errorText(convert.error, t) : null}
      reasonError={error('reason')}
      canSubmit={ready}
      onClose={onClose}
      onSubmit={(reason) =>
        convert.mutate(
          {
            branch_region_id: Number(region),
            branch_district_id: Number(form.district),
            branch_address: form.address.trim(),
            branch_lat: form.lat.trim(),
            branch_lng: form.lng.trim(),
            branch_radius: form.radius.trim() || '5',
            ...(form.owner_phone.trim() ? { owner_phone: form.owner_phone.trim() } : {}),
            reason,
          },
          { onSuccess: (result) => onDone(result.invite_link, result.store_id) },
        )
      }
    >
      <RegionSelect
        value={region}
        onChange={(value) => {
          setRegion(value);
          set('district', '');
        }}
        label={t('stores_convertRegion')}
      />
      <Field label={t('stores_convertDistrict')} error={error('branch_district_id')}>
        {(control) => (
          <select {...control} className={fieldClass} value={form.district} onChange={(event) => set('district', event.target.value)} disabled={!region}>
            <option value="">—</option>
            {(districts.data ?? []).map((district) => (
              <option key={district.id} value={String(district.id)}>
                {district.name}
              </option>
            ))}
          </select>
        )}
      </Field>
      <Field label={t('stores_convertAddress')} error={error('branch_address')}>
        {(control) => <input {...control} type="text" maxLength={255} className={fieldClass} value={form.address} onChange={(event) => set('address', event.target.value)} />}
      </Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label={t('stores_convertLat')} error={error('branch_lat')}>
          {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} value={form.lat} onChange={(event) => set('lat', event.target.value)} />}
        </Field>
        <Field label={t('stores_convertLng')} error={error('branch_lng')}>
          {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} value={form.lng} onChange={(event) => set('lng', event.target.value)} />}
        </Field>
      </div>
      <Field label={t('stores_convertRadius')} error={error('branch_radius')}>
        {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} value={form.radius} onChange={(event) => set('radius', event.target.value)} />}
      </Field>
      <Field label={t('stores_convertOwnerPhone')} hint={application.phone ?? undefined} error={error('owner_phone')}>
        {(control) => <input {...control} type="tel" maxLength={20} className={fieldClass} value={form.owner_phone} placeholder={t('stores_fieldPhoneHint')} onChange={(event) => set('owner_phone', event.target.value)} />}
      </Field>
    </ReasonForm>
  );
}

function InviteLinkDialog({ link, storeId, onClose }: { link: string; storeId: number; onClose: () => void }) {
  const t = useT();
  const [copied, setCopied] = useState(false);
  return (
    <Dialog open title={t('stores_linkReady')} closeLabel={t('close')} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Field label={t('stores_convertLink')}>{(control) => <input {...control} readOnly className={fieldClass} value={link} onFocus={(event) => event.target.select()} />}</Field>
        <Notice message={copied ? t('stores_linkCopied') : null} />
        <div className="flex flex-wrap gap-2 [&>*]:flex-1">
          <Link href={`/stores/${storeId}`} className="inline-flex min-h-11 items-center justify-center rounded-control border border-line px-4 text-body font-bold text-fg hover:bg-surface-2">
            {t('stores_openStore')}
          </Link>
          <Button icon={copied ? Check : Copy} onClick={() => void copyText(link).then((done) => setCopied(done))}>
            {t('stores_linkCopy')}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

function RejectDialog({ application, onClose, onDone }: { application: ApplicationCard; onClose: () => void; onDone: () => void }) {
  const t = useT();
  const reject = useReject(application.uuid);
  return (
    <ReasonForm
      title={t('stores_rejectTitle')}
      text={t('stores_rejectText')}
      submitLabel={t('stores_reject')}
      tone="danger"
      busy={reject.isPending}
      error={reject.error ? errorText(reject.error, t) : null}
      reasonError={fieldError(reject.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => reject.mutate(reason, { onSuccess: onDone })}
    />
  );
}

function ActivateDialog({ application, onClose, onDone }: { application: ApplicationCard; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const move = useMoveApplication(application.uuid);
  return (
    <ReasonForm
      title={t('stores_activateStageTitle')}
      text={t('stores_activateStageText')}
      submitLabel={t('stores_activateStageConfirm')}
      busy={move.isPending}
      error={move.error ? errorText(move.error, t) : null}
      reasonError={fieldError(move.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => move.mutate({ stage: 'active', reason }, { onSuccess: (card) => onDone(t('stores_stageMoved', { label: card.stage_label })) })}
    />
  );
}
