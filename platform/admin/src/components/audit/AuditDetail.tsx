'use client';

import { Button, Dialog } from '@rizqim/ui';
import type { AuditEntry } from '@/lib/api/types';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';

// A value of a changed field as one readable line
function show(value: unknown): string {
  if (value === undefined) return '—';
  if (value === null) return 'null';
  return typeof value === 'string' ? value : JSON.stringify(value);
}

// The fields that differ between the two snapshots (or that exist in only one); the unchanged ones are not worth a row
export function changedFields(before: Record<string, unknown> | null, after: Record<string, unknown> | null): Array<{ field: string; before: unknown; after: unknown }> {
  const names = [...new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})])];
  return names
    .map((field) => ({ field, before: before?.[field], after: after?.[field] }))
    .filter((row) => JSON.stringify(row.before) !== JSON.stringify(row.after));
}

// The detail of one journal entry, with what the fields were and what they became
export function AuditDetail({ entry, onClose }: { entry: AuditEntry; onClose: () => void }) {
  const t = useT();
  const rows = changedFields(entry.before, entry.after);
  const info: Array<[string, string | null]> = [
    [t('audit_what'), entry.action],
    [t('audit_who'), entry.actor?.name ?? t('audit_system')],
    [t('audit_when'), when(entry.created_at)],
    [t('audit_subject'), entry.subject_type ? `${entry.subject_type}${entry.subject_id ? ` #${entry.subject_id}` : ''}` : null],
    [t('audit_storeLabel'), entry.store ? `${entry.store.name} (#${entry.store.id})` : null],
    [t('audit_reason'), entry.reason],
    [t('audit_ip'), entry.ip],
    [t('audit_device'), entry.user_agent],
  ];

  return (
    <Dialog open title={t('audit_detailTitle')} closeLabel={t('close')} onClose={onClose} footer={<Button variant="secondary" onClick={onClose}>{t('close')}</Button>}>
      <dl className="flex flex-col gap-2">
        {info
          .filter((row): row is [string, string] => row[1] !== null && row[1] !== '')
          .map(([name, value]) => (
            <div key={name} className="flex flex-col">
              <dt className="text-caption font-bold text-fg-muted">{name}</dt>
              <dd className="break-words text-footnote text-fg">{value}</dd>
            </div>
          ))}
      </dl>
      <section aria-label={t('audit_changes')} className="flex flex-col gap-2">
        <h3 className="text-footnote font-bold text-fg-muted">{t('audit_changes')}</h3>
        {rows.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('audit_noChanges')}</p>
        ) : (
          <div className="overflow-x-auto rounded-control border border-line">
            <table className="w-full min-w-[20rem] text-left text-footnote">
              <thead>
                <tr className="bg-surface-2 text-fg-muted">
                  <th scope="col" className="px-2 py-1.5 font-bold">
                    {t('audit_field')}
                  </th>
                  <th scope="col" className="px-2 py-1.5 font-bold">
                    {t('audit_before')}
                  </th>
                  <th scope="col" className="px-2 py-1.5 font-bold">
                    {t('audit_after')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.field} className="border-t border-line align-top">
                    <th scope="row" className="break-all px-2 py-1.5 font-mono font-medium text-fg">
                      {row.field}
                    </th>
                    <td className="break-all px-2 py-1.5 text-danger-soft-fg">{show(row.before)}</td>
                    <td className="break-all px-2 py-1.5 text-success-soft-fg">{show(row.after)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="text-caption text-fg-muted">{t('audit_secretNote')}</p>
      </section>
    </Dialog>
  );
}
