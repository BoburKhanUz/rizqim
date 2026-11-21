'use client';

import { useState } from 'react';
import { Button, Dialog, Field, fieldClass, Notice } from '@rizqim/ui';
import { errorText, fieldError } from '@/lib/errors';
import { useImportSyncFile, type SyncImportResult } from '@/lib/api/pos';
import { useT } from '../I18nProvider';
import { ReasonDialog } from './PosParts';

const SHOWN_SKIPPED = 5;

// M5 S4: what the import did, in the server's words plus the totals; `complete: false` asks for the same file once more
function ImportResult({ result }: { result: SyncImportResult }) {
  const t = useT();
  const { totals, skipped } = result;
  return (
    <div role="status" className="flex flex-col gap-2 rounded-card bg-surface-2 p-3">
      <p className="font-bold text-fg">{result.message}</p>
      <ul className="grid grid-cols-2 gap-x-4 gap-y-1 text-footnote text-fg">
        <li>{t('pos_import_applied', { count: totals.applied })}</li>
        <li>{t('pos_import_duplicate', { count: totals.duplicate })}</li>
        <li>{t('pos_import_rejected', { count: totals.rejected })}</li>
        <li>{t('pos_import_conflict', { count: totals.conflict })}</li>
        <li>{t('pos_import_skipped', { count: totals.skipped })}</li>
      </ul>
      {skipped.length > 0 && (
        <ul className="flex flex-col gap-1 text-caption text-fg-muted">
          {skipped.slice(0, SHOWN_SKIPPED).map((row) => (
            <li key={row.line}>{t('pos_import_skippedLine', { line: row.line, message: row.message })}</li>
          ))}
          {skipped.length > SHOWN_SKIPPED && <li>{t('pos_import_skippedMore', { count: skipped.length - SHOWN_SKIPPED })}</li>}
        </ul>
      )}
      {!result.complete && <Notice message={t('pos_import_incomplete', { count: totals.pending })} />}
    </div>
  );
}

// POST /pos/sync-import (stores.manage, step-up through the API caller, required `reason`): the support person uploads the `.rzq`
// file a till wrote; the till is found by the file's header, in any store. The reason typed once stays for a retry of the same file.
export function SyncImportDialog({ onClose }: { onClose: () => void }) {
  const t = useT();
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<SyncImportResult | null>(null);
  const upload = useImportSyncFile();

  // Everything was read: only the result is left to read
  if (result?.complete) {
    return (
      <Dialog open title={t('pos_import_title')} closeLabel={t('close')} onClose={onClose}>
        <div className="flex flex-col gap-4">
          <ImportResult result={result} />
          <Button onClick={onClose}>{t('close')}</Button>
        </div>
      </Dialog>
    );
  }

  return (
    <ReasonDialog
      title={t('pos_import_title')}
      text={t('pos_import_text')}
      submitLabel={result ? t('pos_import_retry') : t('pos_import_submit')}
      busy={upload.isPending}
      canSubmit={file !== null}
      error={upload.error ? errorText(upload.error, t) : null}
      reasonError={fieldError(upload.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => {
        if (file) upload.mutate({ file, reason }, { onSuccess: (answer) => setResult(answer), onError: () => setResult(null) });
      }}
    >
      <Field label={t('pos_import_file')} hint={t('pos_import_fileHint')}>
        {(control) => (
          <input
            {...control}
            type="file"
            accept=".rzq"
            disabled={upload.isPending}
            className={fieldClass}
            onChange={(event) => {
              setFile(event.target.files?.[0] ?? null);
              setResult(null);
              upload.reset();
            }}
          />
        )}
      </Field>
      {result && <ImportResult result={result} />}
    </ReasonDialog>
  );
}
