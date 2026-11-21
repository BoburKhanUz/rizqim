'use client';

import { useRef, useState, type FormEvent } from 'react';
import { Button, Dialog, Field, fieldClass, FormError, Notice } from '@rizqim/ui';
import { useImportSyncFile, type SyncImportResult } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';

const SHOWN_SKIPPED = 5;

// POST /owner/pos/sync/import (M5 S4): the owner or a manager uploads the `.rzq` queue file a till wrote. The server applies
// each operation as that till exactly once, so the same file may always be sent again. The server's `message` is shown as it
// comes (Uzbek), a whole-file refusal too; `complete: false` asks for the same file once more, with a retry button.
export function SyncImportDialog({ onClose }: { onClose: () => void }) {
  const { t } = useI18n();
  const input = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<SyncImportResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const upload = useImportSyncFile();

  const send = (event?: FormEvent) => {
    event?.preventDefault();
    if (!file) {
      setError(t('devices_importFileRequired'));
      return;
    }
    setError(null);
    upload.mutate(file, {
      onSuccess: (answer) => setResult(answer),
      onError: (err) => {
        setResult(null);
        setError(errorText(err, t));
      },
    });
  };

  const done = result?.complete === true;
  const totals = result?.totals;
  const skipped = result?.skipped ?? [];

  return (
    <Dialog open title={t('devices_importTitle')} busy={upload.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={send} noValidate className="flex flex-col gap-4">
        <p className="text-footnote text-fg-muted">{t('devices_importText')}</p>
        <Field label={t('devices_importFile')}>
          {(props) => (
            <input
              {...props}
              ref={input}
              data-autofocus
              type="file"
              accept=".rzq"
              disabled={upload.isPending}
              onChange={(event) => {
                setFile(event.target.files?.[0] ?? null);
                setResult(null);
                setError(null);
              }}
              className={fieldClass}
            />
          )}
        </Field>
        <FormError message={error} />

        {result && totals && (
          <div role="status" className="flex flex-col gap-2 rounded-card bg-surface-2 p-3">
            <p className="font-bold text-fg">{result.message}</p>
            <ul className="grid grid-cols-2 gap-x-4 gap-y-1 text-footnote text-fg">
              <li>{t('devices_importApplied', { count: totals.applied })}</li>
              <li>{t('devices_importDuplicate', { count: totals.duplicate })}</li>
              <li>{t('devices_importRejected', { count: totals.rejected })}</li>
              <li>{t('devices_importConflict', { count: totals.conflict })}</li>
              <li>{t('devices_importSkipped', { count: totals.skipped })}</li>
            </ul>
            {skipped.length > 0 && (
              <ul className="flex flex-col gap-1 text-caption text-fg-muted">
                {skipped.slice(0, SHOWN_SKIPPED).map((row) => (
                  <li key={row.line}>{t('devices_importSkippedLine', { line: row.line, message: row.message })}</li>
                ))}
                {skipped.length > SHOWN_SKIPPED && <li>{t('devices_importSkippedMore', { count: skipped.length - SHOWN_SKIPPED })}</li>}
              </ul>
            )}
            {!result.complete && <Notice message={t('devices_importIncomplete', { count: totals.pending })} />}
          </div>
        )}

        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={upload.isPending}>
            {t('mgmt_close')}
          </Button>
          {!done && (
            <Button type="submit" loading={upload.isPending} disabled={!file}>
              {result ? t('devices_importRetry') : t('devices_importUpload')}
            </Button>
          )}
        </div>
      </form>
    </Dialog>
  );
}
