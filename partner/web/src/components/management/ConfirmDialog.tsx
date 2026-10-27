'use client';

import type { ComponentProps } from 'react';
import { ConfirmDialog as SharedConfirmDialog } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';

type Props = Omit<ComponentProps<typeof SharedConfirmDialog>, 'cancelLabel' | 'closeLabel'>;

// The shared confirmation dialog (@rizqim/ui) with this app's words for "Cancel" and "Close"
export function ConfirmDialog(props: Props) {
  const { t } = useI18n();
  return <SharedConfirmDialog {...props} cancelLabel={t('mgmt_cancel')} closeLabel={t('mgmt_close')} />;
}
