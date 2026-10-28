'use client';

import { useState } from 'react';
import { Check, Copy, Download } from 'lucide-react';
import { Button } from '@rizqim/ui';
import { copyText, downloadText } from '@/lib/clipboard';
import { useT } from '../I18nProvider';

// The recovery codes, shown once: to read, to copy and to save as a .txt file. Used after the 2FA setup and after the codes are
// regenerated. What the person must confirm (the "Saqlab qo'ydim" box) belongs to the screen that shows this.
export function RecoveryCodesPanel({ codes }: { codes: string[] }) {
  const t = useT();
  const [copied, setCopied] = useState(false);

  async function copy() {
    if (await copyText(codes.join('\n'))) {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2500);
    }
  }

  function download() {
    downloadText(t('auth_recoveryFileName'), `${t('auth_recoveryFileHeader')}\n${t('auth_recoveryFileNote')}\n\n${codes.join('\n')}\n`);
  }

  return (
    <div className="flex flex-col gap-3">
      <ol aria-label={t('auth_recoveryList')} className="grid grid-cols-2 gap-2 rounded-card border border-line bg-surface-2 p-3 font-mono text-body text-fg">
        {codes.map((code) => (
          <li key={code} className="select-all text-center">
            {code}
          </li>
        ))}
      </ol>
      <div className="flex flex-wrap gap-2 [&>*]:flex-1">
        <Button variant="secondary" icon={copied ? Check : Copy} onClick={() => void copy()}>
          {copied ? t('copied') : t('copy')}
        </Button>
        <Button variant="secondary" icon={Download} onClick={download}>
          {t('auth_recoveryDownload')}
        </Button>
      </div>
      <p role="status" className="sr-only">
        {copied ? t('copied') : ''}
      </p>
    </div>
  );
}
