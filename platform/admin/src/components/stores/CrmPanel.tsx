'use client';

import { useState, type FormEvent } from 'react';
import { CalendarClock, Check, Plus, RotateCcw } from 'lucide-react';
import { Button, Card, Field, fieldClass, FormError, Pill, Section } from '@rizqim/ui';
import { useAddTask, useApplicationNote, useNextContact, useStoreNote, useUpdateTask, type Crm, type Task } from '@/lib/api/stores';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { day, TASK_LABEL, TASK_TONE, todayInTashkent } from './storesModel';

// CRM-lite of a store or an application (P4-22): notes, tasks with a due date ("payshanba qo'ng'iroq qilish"), and a store's
// next contact date. From the due day a task (and the contact date) shows in Bugun → "Diqqat talab qiladi".
export function CrmPanel({ crm, storeId, applicationUuid, canManage, onNotice }: { crm: Crm; storeId?: number; applicationUuid?: string; canManage: boolean; onNotice: (message: string) => void }) {
  const t = useT();
  return (
    <div className="flex flex-col gap-5">
      {storeId !== undefined && <NextContact storeId={storeId} value={crm.next_contact_on} canManage={canManage} onNotice={onNotice} />}
      <Section title={t('stores_tasks')}>
        <Card className="flex flex-col gap-3">
          {crm.tasks.length === 0 ? <p className="text-footnote text-fg-muted">{t('stores_tasksEmpty')}</p> : <TaskList tasks={crm.tasks} canManage={canManage} />}
          {canManage && <TaskForm storeId={storeId} applicationUuid={applicationUuid} />}
        </Card>
      </Section>
      <Section title={t('stores_notes')}>
        <Card className="flex flex-col gap-3">
          {canManage && <NoteForm storeId={storeId} applicationUuid={applicationUuid} />}
          {crm.notes.length === 0 ? (
            <p className="text-footnote text-fg-muted">{t('stores_notesEmpty')}</p>
          ) : (
            <ul aria-label={t('stores_notes')} className="flex flex-col divide-y divide-line">
              {crm.notes.map((note) => (
                <li key={note.id} className="flex flex-col gap-1 py-2">
                  <p className="whitespace-pre-wrap break-words text-footnote text-fg">{note.body}</p>
                  <p className="text-caption text-fg-muted">
                    {note.author?.name ?? '—'} · {when(note.created_at)}
                  </p>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </div>
  );
}

export function TaskList({ tasks, canManage, showSubject = false }: { tasks: Task[]; canManage: boolean; showSubject?: boolean }) {
  const t = useT();
  const update = useUpdateTask();
  return (
    <ul aria-label={t('stores_tasks')} className="flex flex-col divide-y divide-line">
      {tasks.map((task) => (
        <li key={task.id} className="flex flex-wrap items-center gap-2 py-2">
          <span className="flex min-w-0 flex-1 flex-col gap-0.5">
            <span className="flex flex-wrap items-center gap-2">
              <span className={task.done_at ? 'break-words text-footnote text-fg-muted line-through' : 'break-words text-footnote font-bold text-fg'}>{task.title}</span>
              <Pill tone={TASK_TONE[task.state]}>{t(TASK_LABEL[task.state])}</Pill>
            </span>
            <span className="text-caption text-fg-muted">
              {t('stores_taskMeta', { due: day(task.due_on), assignee: task.assignee?.name ?? '—' })}
              {showSubject && (task.store || task.application) ? ` · ${task.store?.name ?? task.application?.name}` : ''}
            </span>
          </span>
          {canManage && (
            <Button
              variant="ghost"
              icon={task.done_at ? RotateCcw : Check}
              loading={update.isPending && update.variables?.id === task.id}
              onClick={() => update.mutate({ id: task.id, done: !task.done_at })}
            >
              {task.done_at ? t('stores_taskReopen') : t('stores_taskDone')}
            </Button>
          )}
        </li>
      ))}
    </ul>
  );
}

function TaskForm({ storeId, applicationUuid }: { storeId?: number; applicationUuid?: string }) {
  const t = useT();
  const add = useAddTask();
  const [title, setTitle] = useState('');
  const [due, setDue] = useState(todayInTashkent());
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (title.trim().length < 2 || !due || add.isPending) return;
    add.mutate(
      { title: title.trim(), due_on: due, ...(storeId !== undefined ? { store_id: storeId } : { application_uuid: applicationUuid }) },
      { onSuccess: () => setTitle('') },
    );
  };
  return (
    <form onSubmit={submit} className="grid grid-cols-1 gap-2 border-t border-line pt-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
      <Field label={t('stores_taskTitle')} error={fieldError(add.error, 'title')}>
        {(control) => <input {...control} type="text" maxLength={255} className={fieldClass} value={title} onChange={(event) => setTitle(event.target.value)} />}
      </Field>
      <Field label={t('stores_taskDue')} error={fieldError(add.error, 'due_on')}>
        {(control) => <input {...control} type="date" min={todayInTashkent()} className={fieldClass} value={due} onChange={(event) => setDue(event.target.value)} />}
      </Field>
      <Button type="submit" icon={Plus} loading={add.isPending} disabled={title.trim().length < 2 || !due}>
        {t('stores_taskAdd')}
      </Button>
      {add.error && !fieldError(add.error, 'title') && !fieldError(add.error, 'due_on') && <FormError message={errorText(add.error, t)} />}
    </form>
  );
}

function NoteForm({ storeId, applicationUuid }: { storeId?: number; applicationUuid?: string }) {
  const t = useT();
  const storeNote = useStoreNote(storeId ?? 0);
  const applicationNote = useApplicationNote(applicationUuid ?? '');
  const add = storeId !== undefined ? storeNote : applicationNote;
  const [body, setBody] = useState('');
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!body.trim() || add.isPending) return;
    add.mutate(body.trim(), { onSuccess: () => setBody('') });
  };
  return (
    <form onSubmit={submit} className="flex flex-col gap-2">
      <Field label={t('stores_noteLabel')} error={fieldError(add.error, 'body')}>
        {(control) => <textarea {...control} rows={2} maxLength={2000} className={fieldClass} value={body} onChange={(event) => setBody(event.target.value)} />}
      </Field>
      <FormError message={add.error && !fieldError(add.error, 'body') ? errorText(add.error, t) : null} />
      <div>
        <Button type="submit" variant="secondary" icon={Plus} loading={add.isPending} disabled={!body.trim()}>
          {t('stores_noteAdd')}
        </Button>
      </div>
    </form>
  );
}

function NextContact({ storeId, value, canManage, onNotice }: { storeId: number; value: string | null; canManage: boolean; onNotice: (message: string) => void }) {
  const t = useT();
  const save = useNextContact(storeId);
  const [date, setDate] = useState(value ?? '');
  return (
    <Section title={t('stores_nextContactLabel')}>
      <Card className="flex flex-col gap-2">
        {canManage ? (
          <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(event) => {
              event.preventDefault();
              save.mutate(date || null, { onSuccess: () => onNotice(t('stores_nextContactSaved')) });
            }}
          >
            <Field label={t('stores_nextContactLabel')} className="min-w-40 flex-1">
              {(control) => <input {...control} type="date" className={fieldClass} value={date} onChange={(event) => setDate(event.target.value)} />}
            </Field>
            <Button type="submit" icon={CalendarClock} loading={save.isPending} disabled={(date || null) === value}>
              {t('stores_nextContactSave')}
            </Button>
            {value && (
              <Button
                variant="ghost"
                disabled={save.isPending}
                onClick={() => {
                  setDate('');
                  save.mutate(null, { onSuccess: () => onNotice(t('stores_nextContactSaved')) });
                }}
              >
                {t('stores_nextContactClear')}
              </Button>
            )}
          </form>
        ) : (
          <p className="text-footnote text-fg">{day(value)}</p>
        )}
        <FormError message={save.error ? errorText(save.error, t) : null} />
      </Card>
    </Section>
  );
}
