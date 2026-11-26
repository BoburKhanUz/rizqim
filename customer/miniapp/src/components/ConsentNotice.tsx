import { Fragment, useEffect, useState, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { useAuthStore } from '../store/authStore';
import { useConsentStore } from '../store/consentStore';
import { useLanguageStore } from '../store/languageStore';
import { isCustomerRole } from '../lib/roles';
import { legalLinks } from '../lib/legalLinks';
import { IconButton } from './ui';
import { LegalLink } from './LegalLink';

// A translated sentence with {{name}} placeholders, each replaced by a node (here: a link)
function withNodes(template: string, nodes: Record<string, ReactNode>): ReactNode[] {
  return template.split(/(\{\{\w+\}\})/).map((part, index) => {
    const name = /^\{\{(\w+)\}\}$/.exec(part)?.[1];
    return <Fragment key={index}>{name && name in nodes ? nodes[name] : part}</Fragment>;
  });
}

// "Click = consent" (owner decision, docs/legal/README.md): a small line at the bottom of the first screen a new customer
// sees, once per Telegram user (store/consentStore.ts). Not for staff roles. It waits while the phone prompt covers the
// screen (a customer with no phone yet), so it is never "shown" under the modal. Marked as seen as soon as it is shown;
// it stays until the customer closes it or leaves the app, and the next launch has no line.
export function ConsentNotice() {
  const user = useAuthStore((state) => state.user);
  const eligible = Boolean(user && isCustomerRole(user.role) && user.phone);
  return eligible ? <ConsentBar /> : null;
}

function ConsentBar() {
  const { t } = useLanguageStore();
  const [visible, setVisible] = useState(() => !useConsentStore.getState().noticeSeen);

  useEffect(() => {
    if (visible) useConsentStore.getState().markNoticeSeen();
  }, [visible]);

  if (!visible) return null;
  const links = legalLinks();
  const linkClass = 'font-medium text-fg underline underline-offset-2';

  return (
    <div className="px-4 pb-2 animate-fade-in">
      <div
        data-testid="consent-notice"
        role="note"
        className="flex items-center gap-1 rounded-card border border-line bg-surface py-1 pl-3 pr-1 text-caption text-fg-muted shadow-sm"
      >
        <p className="min-w-0 flex-1 py-1">
          {withNodes(t('consentNoticeApp'), {
            terms: <LegalLink href={links.terms} className={linkClass}>{t('consentTermsLink')}</LegalLink>,
            privacy: <LegalLink href={links.privacy} className={linkClass}>{t('consentPrivacyLink')}</LegalLink>,
          })}
        </p>
        <IconButton icon={X} label={t('close')} onClick={() => setVisible(false)} />
      </div>
    </div>
  );
}
