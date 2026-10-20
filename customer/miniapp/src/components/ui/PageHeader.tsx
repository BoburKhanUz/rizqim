import type { ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useLanguageStore } from '../../store/languageStore';
import { isTelegramHost } from '../../lib/twa';
import { IconButton } from '@rizqim/ui';

interface PageHeaderProps {
  title: ReactNode;
  subtitle?: ReactNode;
  // Draw a back arrow. Inside Telegram it is not drawn: Telegram's own BackButton does the job (App wires it)
  back?: boolean;
  action?: ReactNode;
}

// The top of a screen: optional back arrow, a 20px title, an optional subtitle and action.
export function PageHeader({ title, subtitle, back = false, action }: PageHeaderProps) {
  const navigate = useNavigate();
  const { t } = useLanguageStore();
  const showBack = back && !isTelegramHost();
  return (
    <header className="flex items-center gap-2 py-2">
      {showBack && (
        <IconButton
          icon={ArrowLeft}
          label={t('back')}
          className="-ml-2"
          onClick={() => ((window.history.state?.idx ?? 0) > 0 ? navigate(-1) : navigate('/', { replace: true }))}
        />
      )}
      <div className="min-w-0 flex-1">
        <h1 className="truncate text-title font-bold text-fg">{title}</h1>
        {subtitle && <p className="truncate text-footnote text-fg-muted">{subtitle}</p>}
      </div>
      {action}
    </header>
  );
}
