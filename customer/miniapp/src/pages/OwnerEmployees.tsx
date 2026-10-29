import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Check, ChevronDown, Copy, KeyRound, MessageSquare, Phone, Share2, Shield, Trash2, UserPlus, Users } from 'lucide-react';
import {
  useClearManagedStaffPin,
  useCreateManagedStaff,
  useMenuBranches,
  useManagedStaff,
  useRemoveManagedStaff,
  useUpdateManagedStaff,
  type Localized,
  type StaffMember,
} from '@rizqim/staff';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred, selectionChanged } from '../lib/twa';
import { showAlert, showConfirm } from '../lib/dialogs';
import { getApiErrorMessage, getApiFieldError, getApiFieldErrors, logApiError } from '../lib/apiError';
import { useStaffText } from '../hooks/useStaffText';
import { Button, Card, CardSkeletons, EmptyState, Field, IconButton, InlineError, PageHeader, Section, cx, fieldClass } from '../components/ui';
import { Pill } from '../components/staff/Pill';
import { LoginPhoneDialog, PinDialog } from '../components/staff/StaffDialogs';

const TEXT = {
  title: { uz: 'Xodimlar', ru: 'Сотрудники' },
  total: { uz: 'Jami {{n}} ta xodim', ru: 'Всего сотрудников: {{n}}' },
  loading: { uz: 'Xodimlar yuklanmoqda', ru: 'Загрузка сотрудников' },
  addTitle: { uz: "Yangi xodim qo'shish", ru: 'Добавить сотрудника' },
  role: { uz: 'Lavozim', ru: 'Должность' },
  name: { uz: 'Ism-familiya', ru: 'Имя и фамилия' },
  namePlaceholder: { uz: "Xodimning ism-familiyasi", ru: 'Имя и фамилия сотрудника' },
  branch: { uz: 'Filialga biriktirish', ru: 'Филиал' },
  phone: { uz: 'Kirish telefoni (ixtiyoriy)', ru: 'Телефон для входа (необязательно)' },
  phoneHint: { uz: "+998XXXXXXXXX. Xodim shu raqamga kelgan SMS kod bilan kiradi.", ru: '+998XXXXXXXXX. Сотрудник входит по SMS-коду на этот номер.' },
  create: { uz: "Xodim qo'shish", ru: 'Добавить' },
  inviteText: { uz: "Xodimga quyidagi havolani yuboring. U bosganda Telegram orqali ro'yxatdan o'tadi.", ru: 'Отправьте сотруднику ссылку. По ней он зарегистрируется через Telegram.' },
  copy: { uz: 'Nusxa olish', ru: 'Копировать' },
  copied: { uz: 'Nusxalandi', ru: 'Скопировано' },
  share: { uz: 'Ulashish', ru: 'Поделиться' },
  shareText: { uz: 'Siz jamoamizga taklif qilindingiz! Ushbu link orqali tizimga kiring.', ru: 'Вас пригласили в нашу команду! Войдите по этой ссылке.' },
  another: { uz: "Yangi xodim qo'shish", ru: 'Добавить ещё' },
  all: { uz: 'Barcha xodimlar', ru: 'Все сотрудники' },
  empty: { uz: "Hozircha xodimlar yo'q", ru: 'Сотрудников пока нет' },
  emptyText: { uz: "Yuqoridan xodim qo'shing.", ru: 'Добавьте сотрудника выше.' },
  noBranch: { uz: 'Filialsiz', ru: 'Без филиала' },
  pending: { uz: 'Taklif kutilmoqda', ru: 'Ждёт приглашения' },
  telegram: { uz: 'Telegram ulangan', ru: 'Telegram подключён' },
  manage: { uz: 'Boshqarish', ru: 'Управление' },
  manageAria: { uz: '{{name}}: boshqarish', ru: '{{name}}: управление' },
  loginPhone: { uz: 'Kirish raqami', ru: 'Номер для входа' },
  phoneOn: { uz: 'SMS bilan kirish yoqilgan', ru: 'Вход по SMS включён' },
  phoneOnHint: { uz: "Xodim o'zi SMS bilan tasdiqlagan bo'lsa, raqamni bu yerda o'zgartirib bo'lmaydi.", ru: 'Если сотрудник сам подтвердил номер по SMS, здесь его изменить нельзя.' },
  phoneOff: { uz: 'SMS bilan kirish yoqilmagan', ru: 'Вход по SMS не включён' },
  phoneNone: { uz: "Raqam yo'q", ru: 'Номера нет' },
  phoneChange: { uz: "Raqamni o'zgartirish", ru: 'Изменить номер' },
  phoneSet: { uz: "Raqam qo'shish", ru: 'Добавить номер' },
  pin: { uz: 'Terminal PIN kodi', ru: 'PIN-код терминала' },
  pinSet: { uz: "PIN o'rnatilgan", ru: 'PIN задан' },
  pinNone: { uz: "PIN yo'q", ru: 'PIN не задан' },
  pinCode: { uz: 'Kod: {{code}}', ru: 'Код: {{code}}' },
  pinSetAction: { uz: "PIN o'rnatish", ru: 'Задать PIN' },
  pinChange: { uz: 'PIN almashtirish', ru: 'Сменить PIN' },
  pinClear: { uz: "PIN ni o'chirish", ru: 'Сбросить PIN' },
  pinClearTitle: { uz: "PIN ni o'chirish", ru: 'Сбросить PIN' },
  pinClearAsk: { uz: "{{name}} terminalda PIN bilan kira olmaydi, yangi PIN o'rnatilguncha.", ru: '{{name}} не сможет входить на терминал по PIN, пока не будет задан новый.' },
  moveBranch: { uz: "Filialni o'zgartirish", ru: 'Сменить филиал' },
  remove: { uz: "Xodimni o'chirish", ru: 'Удалить сотрудника' },
  removeAria: { uz: "{{name}} xodimini o'chirish", ru: 'Удалить сотрудника {{name}}' },
  removeAsk: { uz: "{{name}} xodimini o'chirmoqchimisiz?", ru: 'Удалить сотрудника {{name}}?' },
  removeTitle: { uz: "Xodimni o'chirish", ru: 'Удаление сотрудника' },
  removeConfirm: { uz: "O'chirish", ru: 'Удалить' },
  noRights: { uz: "Bu xodimni faqat ko'rishingiz mumkin.", ru: 'Этого сотрудника можно только просматривать.' },
  failed: { uz: 'Xatolik yuz berdi', ru: 'Произошла ошибка' },
  moveFailed: { uz: "Filialni o'zgartirib bo'lmadi", ru: 'Не удалось сменить филиал' },
  removeFailed: { uz: "O'chirib bo'lmadi", ru: 'Не удалось удалить' },
} satisfies Record<string, Localized>;

// The roles an owner can give (the backend still decides who may give which: 422 "Bu rolni bera olmaysiz")
const ROLES: ReadonlyArray<{ value: 'manager' | 'cashier' | 'waiter' | 'cook' | 'hostess' | 'courier'; label: Localized }> = [
  { value: 'manager', label: { uz: 'Menejer', ru: 'Менеджер' } },
  { value: 'cashier', label: { uz: 'Kassir', ru: 'Кассир' } },
  { value: 'waiter', label: { uz: 'Ofitsiant', ru: 'Официант' } },
  { value: 'cook', label: { uz: 'Oshpaz', ru: 'Повар' } },
  { value: 'hostess', label: { uz: 'Hostes', ru: 'Хостес' } },
  { value: 'courier', label: { uz: 'Kuryer', ru: 'Курьер' } },
];

type DialogState = { kind: 'pin' | 'phone'; member: StaffMember } | null;

// The state of the member's login phone in words (and a colour), as far as the API says it: whether the phone signs in
// by SMS. Who proved it (the person by SMS, or the owner by writing it) is not in the API's answer.
function LoginPhoneState({ member }: { member: StaffMember }) {
  const tx = useStaffText(TEXT);
  if (member.login_phone_enabled) {
    return (
      <>
        <Pill tone="success" icon={MessageSquare}>
          {tx('phoneOn')}
        </Pill>
        <p className="text-footnote text-fg-muted">{tx('phoneOnHint')}</p>
      </>
    );
  }
  return member.phone ? <Pill tone="warning">{tx('phoneOff')}</Pill> : <Pill>{tx('phoneNone')}</Pill>;
}

function StaffCard({
  member,
  branches,
  roleLabel,
  onDialog,
  onRemove,
  onMove,
  onClearPin,
  busy,
}: {
  member: StaffMember;
  branches: ReadonlyArray<{ id: number; name: string }>;
  roleLabel: string;
  onDialog: (dialog: DialogState) => void;
  onRemove: (member: StaffMember) => void;
  onMove: (member: StaffMember, branchId: number) => void;
  onClearPin: (member: StaffMember) => void;
  busy: boolean;
}) {
  const tx = useStaffText(TEXT);
  const [open, setOpen] = useState(false);
  const panelId = `staff-${member.id}`;

  return (
    <Card className="flex flex-col gap-3 p-3">
      <div className="flex items-center gap-3">
        <span className="flex size-11 shrink-0 items-center justify-center rounded-control bg-brand-soft text-brand-soft-fg">
          <Shield aria-hidden="true" className="size-5" />
        </span>
        <div className="min-w-0 flex-1">
          <h3 className="truncate text-body font-bold text-fg">{member.name}</h3>
          <p className="text-footnote text-fg-muted">
            {roleLabel} • {member.branch?.name ?? tx('noBranch')}
          </p>
        </div>
        <IconButton
          icon={ChevronDown}
          variant="surface"
          label={tx('manageAria', { name: member.name })}
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => {
            selectionChanged();
            setOpen((value) => !value);
          }}
          className={cx(open && '[&_svg]:rotate-180')}
        />
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        {member.invite_pending && <Pill tone="warning">{tx('pending')}</Pill>}
        {member.telegram_linked && <Pill tone="info">{tx('telegram')}</Pill>}
        <Pill tone={member.has_pin ? 'success' : 'neutral'} icon={KeyRound}>
          {member.has_pin ? tx('pinSet') : tx('pinNone')}
        </Pill>
        {member.login_phone_enabled && (
          <Pill tone="success" icon={Phone}>
            {member.phone}
          </Pill>
        )}
      </div>

      {open && (
        <div id={panelId} className="flex flex-col gap-4 border-t border-line pt-3">
          {!member.can_edit && <p className="text-footnote text-fg-muted">{tx('noRights')}</p>}

          <div className="flex flex-col gap-2">
            <h4 className="text-footnote font-bold text-fg-muted">{tx('loginPhone')}</h4>
            {member.phone && <p className="text-body font-medium text-fg">{member.phone}</p>}
            <LoginPhoneState member={member} />
            {member.can_edit && (
              <Button variant="secondary" icon={Phone} onClick={() => onDialog({ kind: 'phone', member })} aria-label={`${member.phone ? tx('phoneChange') : tx('phoneSet')}: ${member.name}`}>
                {member.phone ? tx('phoneChange') : tx('phoneSet')}
              </Button>
            )}
          </div>

          <div className="flex flex-col gap-2">
            <h4 className="text-footnote font-bold text-fg-muted">{tx('pin')}</h4>
            {member.staff_code && <p className="text-footnote text-fg-muted">{tx('pinCode', { code: member.staff_code })}</p>}
            {member.can_edit && (
              <div className="flex flex-wrap gap-2">
                <Button variant="secondary" icon={KeyRound} onClick={() => onDialog({ kind: 'pin', member })} className="grow" aria-label={`${member.has_pin ? tx('pinChange') : tx('pinSetAction')}: ${member.name}`}>
                  {member.has_pin ? tx('pinChange') : tx('pinSetAction')}
                </Button>
                {member.has_pin && (
                  <Button variant="danger" onClick={() => onClearPin(member)} className="grow" aria-label={`${tx('pinClear')}: ${member.name}`}>
                    {tx('pinClear')}
                  </Button>
                )}
              </div>
            )}
          </div>

          {member.can_edit && branches.length > 0 && (
            <Field label={tx('moveBranch')}>
              {(props) => (
                <select {...props} value={member.branch?.id ?? ''} disabled={busy} onChange={(e) => e.target.value && onMove(member, Number(e.target.value))} className={fieldClass}>
                  <option value="" disabled>
                    {tx('noBranch')}
                  </option>
                  {branches.map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.name}
                    </option>
                  ))}
                </select>
              )}
            </Field>
          )}

          {member.can_edit && (
            <Button variant="danger" icon={Trash2} onClick={() => onRemove(member)} aria-label={tx('removeAria', { name: member.name })} fullWidth>
              {tx('remove')}
            </Button>
          )}
        </div>
      )}
    </Card>
  );
}

export default function OwnerEmployees() {
  const tx = useStaffText(TEXT);
  const { data: staff, isLoading, refetch, isRefetching } = useManagedStaff();
  const { data: branches = [] } = useMenuBranches();

  const [inviteLink, setInviteLink] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const copiedTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const [role, setRole] = useState<(typeof ROLES)[number]['value']>('manager');
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [chosenBranchId, setChosenBranchId] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [dialog, setDialog] = useState<DialogState>(null);
  const [workingId, setWorkingId] = useState<number | null>(null);
  // Until the owner picks one, the first branch is selected
  const selectedBranchId = chosenBranchId || (branches[0] ? String(branches[0].id) : '');

  // The "Nusxalandi" timer must not outlive the screen
  useEffect(() => () => clearTimeout(copiedTimer.current), []);

  const roleLabelOf = (member: StaffMember) => member.role_label || ROLES.find((item) => item.value === member.role)?.label.uz || member.role;

  const create = useCreateManagedStaff({
    onSuccess: (created) => {
      notificationOccurred('success');
      setInviteLink(created.invite_link);
      setName('');
      setPhone('');
      setErrors({});
    },
    onError: (err) => {
      logApiError('Failed to add a staff member:', err);
      notificationOccurred('error');
      const found = getApiFieldErrors(err, ['name', 'role', 'branch_id', 'phone']);
      if (Object.keys(found).length > 0) setErrors(found);
      else void showAlert(getApiErrorMessage(err) || tx('failed'));
    },
  });
  const update = useUpdateManagedStaff({
    onSettled: () => setWorkingId(null),
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => {
      logApiError('Failed to move the staff member:', err);
      notificationOccurred('error');
      void showAlert(getApiFieldError(err, 'branch_id') || getApiErrorMessage(err) || tx('moveFailed'));
    },
  });
  const clearPin = useClearManagedStaffPin({
    onSettled: () => setWorkingId(null),
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => {
      logApiError('Failed to clear the PIN:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || tx('failed'));
    },
  });
  const removeMember = useRemoveManagedStaff({
    onSettled: () => setWorkingId(null),
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => {
      logApiError('Failed to remove the staff member:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || tx('removeFailed'));
    },
  });

  const handleCreate = (e: FormEvent) => {
    e.preventDefault();
    if (!name.trim() || !selectedBranchId) return;
    hapticFeedback('medium');
    setErrors({});
    create.mutate({
      name: name.trim(),
      role,
      branch_id: Number(selectedBranchId),
      ...(phone.trim() ? { phone: phone.trim() } : {}),
    });
  };

  const handleRemove = async (member: StaffMember) => {
    const confirmed = await showConfirm(tx('removeAsk', { name: member.name }), { title: tx('removeTitle'), confirmText: tx('removeConfirm'), destructive: true });
    if (!confirmed) return;
    hapticFeedback('heavy');
    setWorkingId(member.id);
    removeMember.mutate(member.id);
  };

  const handleClearPin = async (member: StaffMember) => {
    const confirmed = await showConfirm(tx('pinClearAsk', { name: member.name }), { title: tx('pinClearTitle'), confirmText: tx('pinClear'), destructive: true });
    if (!confirmed) return;
    hapticFeedback('medium');
    setWorkingId(member.id);
    clearPin.mutate(member.id);
  };

  const handleMove = (member: StaffMember, branchId: number) => {
    if (member.branch?.id === branchId) return;
    hapticFeedback('medium');
    setWorkingId(member.id);
    update.mutate({ id: member.id, input: { branch_id: branchId } });
  };

  const copyLink = () => {
    if (!inviteLink) return;
    void navigator.clipboard.writeText(inviteLink);
    setCopied(true);
    notificationOccurred('success');
    clearTimeout(copiedTimer.current);
    copiedTimer.current = setTimeout(() => setCopied(false), 2000);
  };

  const shareLink = () => {
    if (inviteLink && window.Telegram?.WebApp) {
      window.Telegram.WebApp.openTelegramLink(`https://t.me/share/url?url=${encodeURIComponent(inviteLink)}&text=${encodeURIComponent(tx('shareText'))}`);
    }
  };

  if (isLoading && !staff) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={tx('title')} />
        <CardSkeletons count={3} lines={2} label={tx('loading')} />
      </div>
    );
  }

  if (!staff) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={tx('title')} />
        <InlineError onRetry={() => void refetch()} retrying={isRefetching} />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-5 px-4 pt-2 animate-fade-in">
      <PageHeader title={tx('title')} subtitle={tx('total', { n: staff.length })} />

      <Card className="flex flex-col gap-4">
        <h2 className="flex items-center gap-2 text-headline font-bold text-fg">
          <span className="flex size-8 items-center justify-center rounded-control bg-brand-soft text-brand-soft-fg">
            <UserPlus aria-hidden="true" className="size-5" />
          </span>
          {tx('addTitle')}
        </h2>

        {!inviteLink ? (
          <form onSubmit={handleCreate} className="flex flex-col gap-4">
            <Field label={tx('role')} error={errors.role}>
              {(props) => (
                <select {...props} value={role} onChange={(e) => setRole(e.target.value as typeof role)} className={fieldClass}>
                  {ROLES.map((item) => (
                    <option key={item.value} value={item.value}>
                      {item.label.uz}
                    </option>
                  ))}
                </select>
              )}
            </Field>

            <Field label={tx('name')} error={errors.name}>
              {(props) => (
                <input {...props} type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder={tx('namePlaceholder')} className={fieldClass} required />
              )}
            </Field>

            {branches.length > 0 && (
              <Field label={tx('branch')} error={errors.branch_id}>
                {(props) => (
                  <select {...props} value={selectedBranchId} onChange={(e) => setChosenBranchId(e.target.value)} className={fieldClass}>
                    {branches.map((branch) => (
                      <option key={branch.id} value={branch.id}>
                        {branch.name}
                      </option>
                    ))}
                  </select>
                )}
              </Field>
            )}

            <Field label={tx('phone')} hint={tx('phoneHint')} error={errors.phone}>
              {(props) => (
                <input {...props} type="tel" inputMode="tel" autoComplete="off" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+998901234567" className={fieldClass} />
              )}
            </Field>

            <Button type="submit" size="lg" loading={create.isPending} fullWidth>
              {tx('create')}
            </Button>
          </form>
        ) : (
          <div className="flex flex-col gap-3 rounded-card bg-brand-soft p-4 text-brand-soft-fg animate-fade-in">
            <p className="text-body">{tx('inviteText')}</p>
            <div className="flex gap-2">
              <Button variant="secondary" icon={copied ? Check : Copy} onClick={copyLink} className="flex-1">
                {copied ? tx('copied') : tx('copy')}
              </Button>
              <Button icon={Share2} onClick={shareLink} className="flex-1">
                {tx('share')}
              </Button>
            </div>
            <Button variant="ghost" onClick={() => setInviteLink(null)}>
              {tx('another')}
            </Button>
          </div>
        )}
      </Card>

      <Section title={tx('all')}>
        {staff.length === 0 ? (
          <EmptyState icon={Users} title={tx('empty')} text={tx('emptyText')} />
        ) : (
          <ul className="flex flex-col gap-3">
            {staff.map((member) => (
              <li key={member.id}>
                <StaffCard
                  member={member}
                  branches={branches}
                  roleLabel={roleLabelOf(member)}
                  onDialog={setDialog}
                  onRemove={(target) => void handleRemove(target)}
                  onMove={handleMove}
                  onClearPin={(target) => void handleClearPin(target)}
                  busy={workingId === member.id}
                />
              </li>
            ))}
          </ul>
        )}
      </Section>

      {dialog?.kind === 'pin' && <PinDialog member={dialog.member} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'phone' && <LoginPhoneDialog member={dialog.member} onClose={() => setDialog(null)} />}
    </div>
  );
}
