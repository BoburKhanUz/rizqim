import { useId, useState } from 'react';
import { useSetManagedStaffPin, useUpdateManagedStaff, type Localized, type StaffMember } from '@rizqim/staff';
import { hapticFeedback } from '../../lib/telegram';
import { notificationOccurred } from '../../lib/twa';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../../lib/apiError';
import { useStaffText } from '../../hooks/useStaffText';
import { Button, Dialog, fieldClass } from '../ui';

const TEXT = {
  cancel: { uz: 'Bekor qilish', ru: 'Отмена' },
  save: { uz: 'Saqlash', ru: 'Сохранить' },
  pinTitle: { uz: '{{name}}: PIN kod', ru: '{{name}}: PIN-код' },
  pinDescription: { uz: "PIN kod bilan xodim oshxona ekrani va kassada ishga kiradi. Eski PIN o'chib, yangisi ishlaydi.", ru: 'По PIN-коду сотрудник входит на кухонном экране и кассе. Старый PIN заменяется новым.' },
  pinLabel: { uz: 'Yangi PIN kod', ru: 'Новый PIN-код' },
  pinHint: { uz: "4–6 ta raqam. Oson PIN (0000, 1234) qabul qilinmaydi.", ru: '4–6 цифр. Простые PIN (0000, 1234) не принимаются.' },
  pinFailed: { uz: "PIN kodni saqlab bo'lmadi", ru: 'Не удалось сохранить PIN-код' },
  phoneTitle: { uz: '{{name}}: kirish raqami', ru: '{{name}}: номер для входа' },
  phoneDescription: {
    uz: "Xodim shu raqamga kelgan SMS kod bilan kiradi. Raqam o'zgarsa, xodimning eski kirish seanslari yopiladi. Xodimning o'zi SMS bilan tasdiqlagan raqamni bu yerda o'zgartirib bo'lmaydi.",
    ru: 'Сотрудник входит по SMS-коду на этот номер. При смене номера старые сеансы сотрудника закрываются. Номер, который сотрудник сам подтвердил по SMS, здесь изменить нельзя.',
  },
  phoneLabel: { uz: 'Telefon (+998XXXXXXXXX)', ru: 'Телефон (+998XXXXXXXXX)' },
  phoneClear: { uz: "Raqamni o'chirish", ru: 'Удалить номер' },
  phoneFailed: { uz: "Raqamni saqlab bo'lmadi", ru: 'Не удалось сохранить номер' },
} satisfies Record<string, Localized>;

function ErrorLine({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <p role="alert" className="text-footnote font-medium text-danger-soft-fg">
      {message}
    </p>
  );
}

// Sets (or replaces) the terminal PIN of a member of staff: PUT /owner/staff/{id}/pin. Whether the PIN is acceptable is
// the backend's answer, shown under the field.
export function PinDialog({ member, onClose }: { member: Pick<StaffMember, 'id' | 'name'>; onClose: () => void }) {
  const tx = useStaffText(TEXT);
  const [pin, setPin] = useState('');
  const [error, setError] = useState<string | null>(null);
  const fieldId = useId();
  const setPinMutation = useSetManagedStaffPin({
    onSuccess: () => {
      notificationOccurred('success');
      onClose();
    },
    onError: (err) => {
      logApiError('Failed to set the PIN:', err);
      notificationOccurred('error');
      setError(getApiFieldError(err, 'pin') ?? getApiErrorMessage(err) ?? tx('pinFailed'));
    },
  });

  const submit = () => {
    if (pin.length === 0) return;
    setError(null);
    hapticFeedback('medium');
    setPinMutation.mutate({ id: member.id, pin });
  };

  return (
    <Dialog
      open
      hideClose
      busy={setPinMutation.isPending}
      onClose={onClose}
      title={tx('pinTitle', { name: member.name })}
      description={tx('pinDescription')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={setPinMutation.isPending}>
            {tx('cancel')}
          </Button>
          <Button onClick={submit} loading={setPinMutation.isPending} disabled={pin.length === 0}>
            {tx('save')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-1.5">
        <label htmlFor={fieldId} className="text-footnote font-bold text-fg-muted">
          {tx('pinLabel')}
        </label>
        <input
          id={fieldId}
          data-autofocus
          type="password"
          inputMode="numeric"
          autoComplete="off"
          maxLength={12}
          value={pin}
          onChange={(e) => setPin(e.target.value.replace(/\D/g, ''))}
          onKeyDown={(e) => e.key === 'Enter' && submit()}
          className={fieldClass}
        />
        <p className="text-footnote text-fg-muted">{tx('pinHint')}</p>
      </div>
      <ErrorLine message={error} />
    </Dialog>
  );
}

// Writes (or clears) the phone a member of staff signs in with: PATCH /owner/staff/{id}. The backend refuses a phone the
// member proved by SMS themselves (422), and the dialog shows its words.
export function LoginPhoneDialog({ member, onClose }: { member: Pick<StaffMember, 'id' | 'name' | 'phone'>; onClose: () => void }) {
  const tx = useStaffText(TEXT);
  const [phone, setPhone] = useState(member.phone ?? '');
  const [error, setError] = useState<string | null>(null);
  const fieldId = useId();
  const update = useUpdateManagedStaff({
    onSuccess: () => {
      notificationOccurred('success');
      onClose();
    },
    onError: (err) => {
      logApiError('Failed to save the login phone:', err);
      notificationOccurred('error');
      setError(getApiFieldError(err, 'phone') ?? getApiErrorMessage(err) ?? tx('phoneFailed'));
    },
  });

  const save = (value: string | null) => {
    setError(null);
    hapticFeedback('medium');
    update.mutate({ id: member.id, input: { phone: value } });
  };

  return (
    <Dialog
      open
      hideClose
      busy={update.isPending}
      onClose={onClose}
      title={tx('phoneTitle', { name: member.name })}
      description={tx('phoneDescription')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={update.isPending}>
            {tx('cancel')}
          </Button>
          <Button onClick={() => save(phone.trim() === '' ? null : phone.trim())} loading={update.isPending}>
            {tx('save')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-1.5">
        <label htmlFor={fieldId} className="text-footnote font-bold text-fg-muted">
          {tx('phoneLabel')}
        </label>
        <input
          id={fieldId}
          data-autofocus
          type="tel"
          inputMode="tel"
          autoComplete="off"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          placeholder="+998901234567"
          className={fieldClass}
        />
      </div>
      {member.phone && (
        <Button variant="ghost" onClick={() => save(null)} disabled={update.isPending}>
          {tx('phoneClear')}
        </Button>
      )}
      <ErrorLine message={error} />
    </Dialog>
  );
}
