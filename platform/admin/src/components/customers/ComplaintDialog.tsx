'use client';

import Link from 'next/link';
import { useState, type FormEvent, type ReactNode } from 'react';
import { CheckCircle2, ExternalLink, Hand, RotateCcw, Send } from 'lucide-react';
import { Button, CardSkeletons, Dialog, Field, fieldClass, FormError, InlineError, Pill } from '@rizqim/ui';
import { useCannedReplies, useComplaint, useReplyToComplaint, useResolveComplaint, useUpdateComplaint, type ComplaintCard, type Named } from '@/lib/api/customers';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { SUPPORT_TONE, uzs } from './customersModel';

type Mode = 'view' | 'reply' | 'resolve';

// One complaint: what the customer wrote, the order and the store's own answer, what support sent, the resolution; and the
// actions the backend offers this member (`actions`): take it, give it to a colleague, reply through the bot (from a tayyor
// javob or by hand), resolve with a note, reopen.
export function ComplaintDialog({ uuid, assignees, onClose, onDone }: { uuid: string; assignees: Named[]; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const complaint = useComplaint(uuid);
  const [mode, setMode] = useState<Mode>('view');
  const card = complaint.data;

  const title = mode === 'reply' ? t('customers_replyTitle') : mode === 'resolve' ? t('customers_resolveTitle') : card ? t('customers_complaintTitle', { type: card.type_label }) : t('customers_complaint');

  return (
    <Dialog open title={title} closeLabel={t('close')} onClose={mode === 'view' ? onClose : () => setMode('view')}>
      {complaint.isError ? (
        <InlineError title={t('customers_loadError')} message={errorText(complaint.error, t)} retryLabel={t('retry')} retrying={complaint.isFetching} onRetry={() => void complaint.refetch()} />
      ) : !card ? (
        <CardSkeletons count={1} lines={4} label={t('loading')} />
      ) : mode === 'reply' ? (
        <ReplyForm card={card} onCancel={() => setMode('view')} onDone={(message) => {
          setMode('view');
          onDone(message);
        }} />
      ) : mode === 'resolve' ? (
        <ResolveForm card={card} onCancel={() => setMode('view')} onDone={(message) => {
          setMode('view');
          onDone(message);
        }} />
      ) : (
        <ComplaintView card={card} assignees={assignees} onMode={setMode} onDone={onDone} />
      )}
    </Dialog>
  );
}

function Block({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-1.5">
      <h3 className="text-footnote font-bold text-fg-muted">{title}</h3>
      {children}
    </section>
  );
}

function ComplaintView({ card, assignees, onMode, onDone }: { card: ComplaintCard; assignees: Named[]; onMode: (mode: Mode) => void; onDone: (message: string) => void }) {
  const t = useT();
  const update = useUpdateComplaint(card.uuid);
  const { actions } = card;
  const take = () => update.mutate({ support_status: 'in_progress' }, { onSuccess: () => onDone(t('customers_takeDone')) });
  const reopen = () => update.mutate({ support_status: 'in_progress' }, { onSuccess: () => onDone(t('customers_reopenDone')) });
  const people = card.assignee && !assignees.some((person) => person.id === card.assignee!.id) ? [card.assignee, ...assignees] : assignees;

  return (
    <div className="flex flex-col gap-4 text-footnote">
      <div className="flex flex-wrap items-center gap-2">
        <Pill tone={SUPPORT_TONE[card.support_status]}>{card.support_status_label}</Pill>
        {card.waiting && <Pill tone="danger">{t('customers_waiting')}</Pill>}
        {card.escalated && <Pill tone="warning">{t('customers_escalated')}</Pill>}
        <span className="text-fg-muted">{card.store_flow_label}</span>
      </div>
      <p className="whitespace-pre-wrap break-words text-body text-fg">{card.description}</p>
      <p className="text-fg-muted">{t('customers_filedAt', { time: when(card.created_at) })}</p>

      <Block title={t('customers_customer')}>
        {card.customer ? (
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <Link href={card.customer.href} className="inline-flex min-h-11 items-center gap-1 font-bold text-brand hover:underline">
              {card.customer.name}
              <ExternalLink aria-hidden="true" className="size-3.5" />
            </Link>
            <span className="text-fg">{card.customer.phone ?? t('customers_noPhone')}</span>
            <span className="text-fg-muted">{t(card.customer.telegram_linked ? 'customers_telegramYes' : 'customers_telegramNo')}</span>
            {card.customer.blocked && <Pill tone="danger">{t('customers_blocked')}</Pill>}
          </div>
        ) : (
          <p className="text-fg-muted">—</p>
        )}
      </Block>

      {card.order && (
        <Block title={t('customers_order')}>
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <Link href={card.order.href} className="inline-flex min-h-11 items-center gap-1 font-mono font-bold text-brand hover:underline">
              #{card.order.short_code ?? card.order.uuid.slice(0, 8)}
              <ExternalLink aria-hidden="true" className="size-3.5" />
            </Link>
            <span className="text-fg">{card.order.status_label}</span>
            <span className="text-fg">{uzs(card.order.total)}</span>
            {card.store && <span className="text-fg-muted">{card.store.name}</span>}
          </div>
        </Block>
      )}

      <Block title={t('customers_storeReply')}>
        <p className="whitespace-pre-wrap break-words text-fg">{card.store_reply ?? t('customers_storeNoReply')}</p>
      </Block>

      <Block title={t('customers_replies')}>
        {card.replies.length === 0 ? (
          <p className="text-fg-muted">{t('customers_repliesEmpty')}</p>
        ) : (
          <ul aria-label={t('customers_replies')} className="flex flex-col gap-2">
            {card.replies.map((reply) => (
              <li key={reply.id} className="rounded-control bg-surface-2 p-3">
                <p className="whitespace-pre-wrap break-words text-fg">{reply.body}</p>
                <p className="mt-1 text-caption text-fg-muted">{[reply.author?.name, when(reply.created_at)].filter(Boolean).join(' · ')}</p>
              </li>
            ))}
          </ul>
        )}
      </Block>

      {card.resolution && (
        <Block title={t('customers_resolution')}>
          <p className="whitespace-pre-wrap break-words text-fg">{card.resolution.note ?? '—'}</p>
          <p className="text-caption text-fg-muted">{[card.resolution.by?.name, when(card.resolution.at)].filter(Boolean).join(' · ')}</p>
        </Block>
      )}

      {actions.manage ? (
        <Field label={t('customers_assigneeLabel')} error={fieldError(update.error, 'assigned_to')}>
          {(control) => (
            <select
              {...control}
              className={fieldClass}
              value={card.assignee?.id ?? ''}
              disabled={update.isPending}
              onChange={(event) => update.mutate({ assigned_to: event.target.value ? Number(event.target.value) : null }, { onSuccess: () => onDone(t('customers_assignDone')) })}
            >
              <option value="">{t('customers_noAssignee')}</option>
              {people.map((person) => (
                <option key={person.id} value={person.id}>
                  {person.name}
                </option>
              ))}
            </select>
          )}
        </Field>
      ) : (
        <p className="text-fg-muted">{card.assignee ? t('customers_assignee', { name: card.assignee.name }) : t('customers_noAssignee')}</p>
      )}
      <FormError message={update.error ? errorText(update.error, t) : null} />

      {actions.manage && (
        <div role="group" aria-label={t('customers_actions')} className="flex flex-wrap gap-2">
          {card.support_status === 'new' && (
            <Button variant="secondary" icon={Hand} loading={update.isPending} onClick={take}>
              {t('customers_take')}
            </Button>
          )}
          {actions.reply && (
            <Button icon={Send} onClick={() => onMode('reply')}>
              {t('customers_reply')}
            </Button>
          )}
          {actions.resolve && (
            <Button variant="secondary" icon={CheckCircle2} onClick={() => onMode('resolve')}>
              {t('customers_resolve')}
            </Button>
          )}
          {actions.reopen && (
            <Button variant="secondary" icon={RotateCcw} loading={update.isPending} onClick={reopen}>
              {t('customers_reopen')}
            </Button>
          )}
        </div>
      )}
      {actions.manage && !actions.reply && !actions.reopen && card.customer && !card.customer.telegram_linked && <p className="text-caption text-fg-muted">{t('customers_noTelegramHint')}</p>}

      {card.history.length > 0 && (
        <Block title={t('customers_history')}>
          <ul aria-label={t('customers_history')} className="flex flex-col gap-1">
            {card.history.map((entry) => (
              <li key={entry.id} className="text-caption text-fg-muted">
                {[when(entry.created_at), entry.actor?.name, t(historyLabel(entry.action))].filter(Boolean).join(' · ')}
              </li>
            ))}
          </ul>
        </Block>
      )}
    </div>
  );
}

function historyLabel(action: string) {
  switch (action) {
    case 'admin.support.complaint_replied':
      return 'customers_h_replied' as const;
    case 'admin.support.complaint_resolved':
      return 'customers_h_resolved' as const;
    default:
      return 'customers_h_updated' as const;
  }
}

function ReplyForm({ card, onCancel, onDone }: { card: ComplaintCard; onCancel: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const replies = useCannedReplies();
  const send = useReplyToComplaint(card.uuid);
  const [text, setText] = useState('');
  const [cannedId, setCannedId] = useState<number | null>(null);
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (send.isPending || text.trim() === '') return;
    send.mutate({ text: text.trim(), canned_reply_id: cannedId }, { onSuccess: () => onDone(t('customers_replyDone')) });
  };
  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('customers_replyText', { name: card.customer?.name ?? '—' })}</p>
      <Field label={t('customers_canned')}>
        {(control) => (
          <select
            {...control}
            className={fieldClass}
            value={cannedId ?? ''}
            onChange={(event) => {
              const chosen = (replies.data ?? []).find((reply) => reply.id === Number(event.target.value));
              setCannedId(chosen?.id ?? null);
              if (chosen) setText(chosen.body);
            }}
          >
            <option value="">{(replies.data ?? []).length === 0 ? t('customers_cannedNone') : t('customers_cannedPick')}</option>
            {(replies.data ?? []).map((reply) => (
              <option key={reply.id} value={reply.id}>
                {reply.title}
              </option>
            ))}
          </select>
        )}
      </Field>
      <Field label={t('customers_replyLabel')} hint={t('customers_replyHint')} error={fieldError(send.error, 'text')}>
        {(control) => <textarea {...control} data-autofocus rows={5} maxLength={1000} className={fieldClass} value={text} onChange={(event) => setText(event.target.value)} />}
      </Field>
      <FormError message={send.error ? errorText(send.error, t) : null} />
      <div className="flex gap-2 [&>*]:flex-1">
        <Button variant="secondary" onClick={onCancel} disabled={send.isPending}>
          {t('cancel')}
        </Button>
        <Button type="submit" icon={Send} loading={send.isPending} disabled={text.trim() === ''}>
          {t('customers_replySend')}
        </Button>
      </div>
    </form>
  );
}

function ResolveForm({ card, onCancel, onDone }: { card: ComplaintCard; onCancel: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const resolve = useResolveComplaint(card.uuid);
  const [note, setNote] = useState('');
  const valid = note.trim().length >= 3;
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!resolve.isPending && valid) resolve.mutate(note.trim(), { onSuccess: () => onDone(t('customers_resolveDone')) });
  };
  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('customers_resolveText')}</p>
      <Field label={t('customers_resolveNote')} hint={t('customers_resolveHint')} error={fieldError(resolve.error, 'note')}>
        {(control) => <textarea {...control} data-autofocus required minLength={3} maxLength={1000} rows={3} className={fieldClass} value={note} onChange={(event) => setNote(event.target.value)} />}
      </Field>
      <FormError message={resolve.error ? errorText(resolve.error, t) : null} />
      <div className="flex gap-2 [&>*]:flex-1">
        <Button variant="secondary" onClick={onCancel} disabled={resolve.isPending}>
          {t('cancel')}
        </Button>
        <Button type="submit" icon={CheckCircle2} loading={resolve.isPending} disabled={!valid}>
          {t('customers_resolveConfirm')}
        </Button>
      </div>
    </form>
  );
}
