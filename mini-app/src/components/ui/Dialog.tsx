import { useEffect, useId, useRef, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { useLanguageStore } from '../../store/languageStore';
import { IconButton } from './IconButton';
import { cx } from './cx';

interface DialogProps {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  description?: ReactNode;
  children?: ReactNode;
  // Buttons at the bottom, e.g. <Button variant="secondary">Yopish</Button><Button>Saqlash</Button>
  footer?: ReactNode;
  // sheet: slides up from the bottom (forms, pickers, long content); center: a small question or message
  variant?: 'sheet' | 'center';
  // alertdialog: a question the user has to answer (confirmations); dismissing it by tapping outside is off
  role?: 'dialog' | 'alertdialog';
  // While busy (a request is running) it cannot be closed
  busy?: boolean;
  hideClose?: boolean;
}

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// The one modal of the app (docs/DESIGN.md): a bottom sheet or a centred dialog over a scrim. Escape and the close
// button close it, focus moves into it and back to where it was, the page behind does not scroll.
export function Dialog({ open, onClose, title, description, children, footer, variant = 'sheet', role = 'dialog', busy = false, hideClose = false }: DialogProps) {
  const titleId = useId();
  const descriptionId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const { t } = useLanguageStore();
  const onCloseRef = useRef(onClose);
  const busyRef = useRef(busy);

  useEffect(() => {
    onCloseRef.current = onClose;
    busyRef.current = busy;
  });

  useEffect(() => {
    if (!open) return;
    const previous = document.activeElement as HTMLElement | null;
    const panel = panelRef.current;
    (panel?.querySelector<HTMLElement>('[data-autofocus]') ?? panel?.querySelector<HTMLElement>(FOCUSABLE) ?? panel)?.focus();

    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !busyRef.current) {
        event.stopPropagation();
        onCloseRef.current();
      }
      if (event.key === 'Tab' && panel) {
        // Keep Tab inside the dialog
        const items = Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE));
        if (items.length === 0) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = overflow;
      previous?.focus?.();
    };
  }, [open]);

  if (!open) return null;

  const isSheet = variant === 'sheet';
  return createPortal(
    <div
      className={cx('fixed inset-0 z-[120] flex justify-center bg-scrim animate-fade-in', isSheet ? 'items-end' : 'items-center p-4')}
      onClick={(event) => {
        if (event.target === event.currentTarget && role === 'dialog' && !busy) onClose();
      }}
    >
      <div
        ref={panelRef}
        role={role}
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        tabIndex={-1}
        className={cx(
          'flex max-h-[90vh] w-full max-w-md flex-col gap-4 overflow-y-auto bg-surface p-5 text-fg shadow-2xl outline-none',
          isSheet ? 'rounded-t-sheet pb-[calc(20px+var(--rz-inset-bottom))] animate-slide-up' : 'max-w-sm rounded-sheet animate-scale-up',
        )}
      >
        <div className="flex items-start gap-2">
          <div className="min-w-0 flex-1 pt-2">
            <h2 id={titleId} className="text-headline font-bold text-fg">
              {title}
            </h2>
            {description && (
              <p id={descriptionId} className="mt-1 text-footnote text-fg-muted">
                {description}
              </p>
            )}
          </div>
          {!hideClose && <IconButton icon={X} label={t('close')} onClick={onClose} disabled={busy} className="-mr-2 -mt-1" />}
        </div>
        {children}
        {footer && <div className="flex gap-2 [&>*]:flex-1">{footer}</div>}
      </div>
    </div>,
    document.body,
  );
}
