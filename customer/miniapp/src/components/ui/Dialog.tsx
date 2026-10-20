import type { ComponentProps } from 'react';
import { Dialog as BaseDialog } from '@rizqim/ui';
import { useLanguageStore } from '../../store/languageStore';

// The one modal of the app (docs/DESIGN.md): the look and behaviour are @rizqim/ui's; this binds the Mini App's
// language to the close button's name.
export function Dialog(props: Omit<ComponentProps<typeof BaseDialog>, 'closeLabel'>) {
  const { t } = useLanguageStore();
  return <BaseDialog closeLabel={t('close')} {...props} />;
}
