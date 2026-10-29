'use client';

import { useState, type FormEvent } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, CardSkeletons, Dialog, Field, fieldClass, FormError, InlineError } from '@rizqim/ui';
import { useCannedReplies, useCreateCannedReply, useDeleteCannedReply, useUpdateCannedReply, type CannedReply } from '@/lib/api/customers';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';

// Tayyor javoblar: texts a reply starts from. Everyone with support.view reads them; support.manage adds, edits and deletes
// (a reply keeps its own copy of the text, so editing one never changes what a customer already got).
export function CannedRepliesDialog({ canManage, onClose }: { canManage: boolean; onClose: () => void }) {
  const t = useT();
  const replies = useCannedReplies();
  const [editing, setEditing] = useState<CannedReply | 'new' | null>(null);
  const [deleting, setDeleting] = useState<number | null>(null);
  const remove = useDeleteCannedReply();

  return (
    <Dialog open title={t('customers_cannedTitle')} description={t('customers_cannedText')} closeLabel={t('close')} onClose={editing ? () => setEditing(null) : onClose}>
      {editing ? (
        <CannedForm key={editing === 'new' ? 'new' : editing.id} reply={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />
      ) : replies.isError ? (
        <InlineError title={t('customers_loadError')} message={errorText(replies.error, t)} retryLabel={t('retry')} retrying={replies.isFetching} onRetry={() => void replies.refetch()} />
      ) : !replies.data ? (
        <CardSkeletons count={2} lines={2} label={t('loading')} />
      ) : (
        <div className="flex flex-col gap-3">
          {replies.data.length === 0 ? (
            <p className="text-footnote text-fg-muted">{t('customers_cannedEmpty')}</p>
          ) : (
            <ul aria-label={t('customers_cannedTitle')} className="flex flex-col gap-2">
              {replies.data.map((reply) => (
                <li key={reply.id} className="flex flex-col gap-2 rounded-control bg-surface-2 p-3">
                  <p className="text-footnote font-bold text-fg">{reply.title}</p>
                  <p className="whitespace-pre-wrap break-words text-footnote text-fg">{reply.body}</p>
                  {canManage && (
                    <div className="flex flex-wrap gap-2">
                      <Button variant="secondary" icon={Pencil} onClick={() => setEditing(reply)}>
                        {t('customers_cannedEdit')}
                      </Button>
                      {deleting === reply.id ? (
                        <Button variant="danger" icon={Trash2} loading={remove.isPending} onClick={() => remove.mutate(reply.id, { onSuccess: () => setDeleting(null) })}>
                          {t('customers_cannedDeleteConfirm')}
                        </Button>
                      ) : (
                        <Button variant="secondary" icon={Trash2} onClick={() => setDeleting(reply.id)}>
                          {t('customers_cannedDelete')}
                        </Button>
                      )}
                    </div>
                  )}
                </li>
              ))}
            </ul>
          )}
          <FormError message={remove.error ? errorText(remove.error, t) : null} />
          {canManage && (
            <Button icon={Plus} onClick={() => setEditing('new')}>
              {t('customers_cannedAdd')}
            </Button>
          )}
        </div>
      )}
    </Dialog>
  );
}

function CannedForm({ reply, onDone }: { reply: CannedReply | null; onDone: () => void }) {
  const t = useT();
  const create = useCreateCannedReply();
  const update = useUpdateCannedReply();
  const save = reply ? update : create;
  const [title, setTitle] = useState(reply?.title ?? '');
  const [body, setBody] = useState(reply?.body ?? '');
  const valid = title.trim() !== '' && body.trim() !== '';
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!valid || save.isPending) return;
    if (reply) update.mutate({ id: reply.id, title: title.trim(), body: body.trim() }, { onSuccess: onDone });
    else create.mutate({ title: title.trim(), body: body.trim() }, { onSuccess: onDone });
  };
  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Field label={t('customers_cannedName')} error={fieldError(save.error, 'title')}>
        {(control) => <input {...control} data-autofocus maxLength={120} className={fieldClass} value={title} onChange={(event) => setTitle(event.target.value)} />}
      </Field>
      <Field label={t('customers_cannedBody')} error={fieldError(save.error, 'body')}>
        {(control) => <textarea {...control} rows={4} maxLength={1000} className={fieldClass} value={body} onChange={(event) => setBody(event.target.value)} />}
      </Field>
      <FormError message={save.error ? errorText(save.error, t) : null} />
      <div className="flex gap-2 [&>*]:flex-1">
        <Button variant="secondary" onClick={onDone} disabled={save.isPending}>
          {t('cancel')}
        </Button>
        <Button type="submit" loading={save.isPending} disabled={!valid}>
          {t('save')}
        </Button>
      </div>
    </form>
  );
}
