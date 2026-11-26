import type { MouseEvent, ReactNode } from 'react';
import { openExternalLink } from '../lib/openMap';

// A link to one of the site's legal pages. A real <a href> (the address is visible and copyable), but the tap opens it
// through Telegram (openLink: the in-app browser / the system one), never inside the Mini App's own webview.
export function LegalLink({ href, className, children }: { href: string; className?: string; children: ReactNode }) {
  const open = (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault();
    openExternalLink(href);
  };
  return (
    <a href={href} target="_blank" rel="noopener noreferrer" onClick={open} className={className}>
      {children}
    </a>
  );
}
