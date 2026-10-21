import { useState } from 'react';
import { Clock, MapPin, Pencil, Phone } from 'lucide-react';
import { useMenuBranches, useUpdateBranch, type Localized, type MenuBranch } from '@rizqim/staff';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { getApiErrorMessage, getApiFieldErrors, logApiError } from '../lib/apiError';
import { useMainButton } from '../hooks/useMainButton';
import { useStaffText } from '../hooks/useStaffText';
import { Button, Card, CardSkeletons, EmptyState, Field, InlineError, PageHeader, fieldClass } from '../components/ui';
import { Pill } from '../components/staff/Pill';
import { Switch } from '../components/staff/Switch';

const TEXT = {
  title: { uz: 'Filiallar', ru: 'Филиалы' },
  subtitle: { uz: 'Ish vaqti va holati', ru: 'Часы работы и статус' },
  empty: { uz: "Filial yo'q", ru: 'Филиалов нет' },
  openNow: { uz: 'Hozir ochiq', ru: 'Сейчас открыт' },
  closedNow: { uz: 'Hozir yopiq', ru: 'Сейчас закрыт' },
  inactive: { uz: 'Faol emas', ru: 'Не активен' },
  tempClosed: { uz: 'Vaqtincha yopiq', ru: 'Временно закрыт' },
  allDay: { uz: "Sutka bo'yi", ru: 'Круглосуточно' },
  hours: { uz: 'Ish vaqti', ru: 'Часы работы' },
  overnight: { uz: 'Tungi smena', ru: 'Ночная смена' },
  edit: { uz: 'Tahrirlash', ru: 'Изменить' },
  editAria: { uz: '{{name}}: ish vaqtini tahrirlash', ru: '{{name}}: изменить часы работы' },
  cancel: { uz: 'Bekor qilish', ru: 'Отмена' },
  save: { uz: 'Saqlash', ru: 'Сохранить' },
  allDaySwitch: { uz: "Sutka bo'yi ochiq", ru: 'Открыт круглосуточно' },
  opens: { uz: 'Ochilish vaqti', ru: 'Время открытия' },
  closes: { uz: 'Yopilish vaqti', ru: 'Время закрытия' },
  tempSwitch: { uz: 'Vaqtincha yopiq', ru: 'Временно закрыт' },
  tempHint: { uz: 'Yopiq filialga yetkazib berish buyurtmasi tushmaydi', ru: 'В закрытый филиал не поступают заказы на доставку' },
  failed: { uz: "Filialni saqlab bo'lmadi", ru: 'Не удалось сохранить филиал' },
  loading: { uz: 'Filiallar yuklanmoqda', ru: 'Загрузка филиалов' },
  webHint: { uz: 'Manzil, hudud va radiusni web panelda tahrirlang.', ru: 'Адрес, зону и радиус меняйте в веб-панели.' },
} satisfies Record<string, Localized>;

const hhmm = (value: string | null | undefined) => (value ? value.slice(0, 5) : '');

interface Draft {
  allDay: boolean;
  open: string;
  close: string;
  temporarilyClosed: boolean;
}

function toDraft(branch: MenuBranch): Draft {
  return {
    allDay: branch.working_hours.all_day,
    open: hhmm(branch.working_hours.open) || '09:00',
    close: hhmm(branch.working_hours.close) || '22:00',
    temporarilyClosed: branch.status === 'temporarily_closed',
  };
}

function BranchCard({ branch, editing, onEdit }: { branch: MenuBranch; editing: boolean; onEdit: () => void }) {
  const tx = useStaffText(TEXT);
  const hours = branch.working_hours;
  const inactive = branch.status === 'inactive' || !branch.is_active;
  return (
    <Card className="flex flex-col gap-3">
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <h2 className="truncate text-headline font-bold text-fg">{branch.name}</h2>
          {branch.address && (
            <p className="mt-0.5 flex items-start gap-1.5 text-footnote text-fg-muted">
              <MapPin aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
              <span className="line-clamp-2">{branch.address}</span>
            </p>
          )}
          {branch.phone && (
            <p className="mt-0.5 flex items-center gap-1.5 text-footnote text-fg-muted">
              <Phone aria-hidden="true" className="size-3.5 shrink-0" />
              {branch.phone}
            </p>
          )}
        </div>
        {inactive ? (
          <Pill tone="neutral">{tx('inactive')}</Pill>
        ) : branch.status === 'temporarily_closed' ? (
          <Pill tone="warning">{tx('tempClosed')}</Pill>
        ) : branch.is_open_now ? (
          <Pill tone="success">{tx('openNow')}</Pill>
        ) : (
          <Pill tone="danger">{tx('closedNow')}</Pill>
        )}
      </div>

      <div className="flex items-center gap-2 rounded-control bg-surface-2 px-3 py-2.5">
        <Clock aria-hidden="true" className="size-4 shrink-0 text-brand" />
        <span className="text-footnote text-fg-muted">{tx('hours')}:</span>
        <span className="text-body font-bold text-fg">{hours.all_day ? tx('allDay') : `${hhmm(hours.open)} – ${hhmm(hours.close)}`}</span>
        {!hours.all_day && hours.overnight && <Pill tone="info">{tx('overnight')}</Pill>}
      </div>

      {!editing && (
        <Button variant="secondary" icon={Pencil} onClick={onEdit} fullWidth aria-label={tx('editAria', { name: branch.name })}>
          {tx('edit')}
        </Button>
      )}
    </Card>
  );
}

function BranchEditor({ branch, onDone }: { branch: MenuBranch; onDone: () => void }) {
  const tx = useStaffText(TEXT);
  const [draft, setDraft] = useState<Draft>(() => toDraft(branch));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const inactive = branch.status === 'inactive' || !branch.is_active;

  const update = useUpdateBranch({
    onSuccess: () => {
      notificationOccurred('success');
      onDone();
    },
    onError: (err) => {
      logApiError('Failed to save the branch:', err);
      notificationOccurred('error');
      const found = getApiFieldErrors(err, ['open_time', 'close_time', 'status']);
      if (Object.keys(found).length > 0) setErrors(found);
      else void showAlert(getApiErrorMessage(err) || tx('failed'));
    },
  });

  const save = () => {
    hapticFeedback('medium');
    setErrors({});
    update.mutate({
      id: branch.id,
      input: {
        // open_time and close_time go together; both empty is "open all day"
        open_time: draft.allDay ? null : draft.open,
        close_time: draft.allDay ? null : draft.close,
        ...(inactive ? {} : { status: draft.temporarilyClosed ? 'temporarily_closed' : 'active' }),
      },
    });
  };

  // The one action of the screen while a branch is being edited
  useMainButton({ text: tx('save'), onClick: save, progress: update.isPending, enabled: draft.allDay || (draft.open !== '' && draft.close !== '') });

  return (
    <Card className="flex flex-col gap-4 border-brand">
      <h3 className="text-body font-bold text-fg">{branch.name}</h3>
      <div className="flex items-center justify-between gap-3">
        <span className="text-body font-medium text-fg">{tx('allDaySwitch')}</span>
        <Switch checked={draft.allDay} onChange={(allDay) => setDraft({ ...draft, allDay })} label={tx('allDaySwitch')} />
      </div>
      {!draft.allDay && (
        <div className="grid grid-cols-2 gap-4">
          <Field label={tx('opens')} error={errors.open_time}>
            {(props) => <input {...props} type="time" value={draft.open} onChange={(e) => setDraft({ ...draft, open: e.target.value })} className={fieldClass} />}
          </Field>
          <Field label={tx('closes')} error={errors.close_time}>
            {(props) => <input {...props} type="time" value={draft.close} onChange={(e) => setDraft({ ...draft, close: e.target.value })} className={fieldClass} />}
          </Field>
        </div>
      )}
      {!inactive && (
        <div className="flex items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="text-body font-medium text-fg">{tx('tempSwitch')}</p>
            <p className="text-footnote text-fg-muted">{tx('tempHint')}</p>
            {errors.status && (
              <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
                {errors.status}
              </p>
            )}
          </div>
          <Switch checked={draft.temporarilyClosed} onChange={(temporarilyClosed) => setDraft({ ...draft, temporarilyClosed })} label={tx('tempSwitch')} />
        </div>
      )}
      <Button variant="secondary" onClick={onDone} disabled={update.isPending} fullWidth>
        {tx('cancel')}
      </Button>
    </Card>
  );
}

// The branches of the store with their working hours and open/closed state (GET /owner/branches). An owner edits any, a
// manager their own. Address, delivery radius and zones are drawn on the web panel.
export default function OwnerBranches() {
  const tx = useStaffText(TEXT);
  const { data: branches, isLoading, error, refetch, isRefetching } = useMenuBranches();
  const [editingId, setEditingId] = useState<number | null>(null);

  if (isLoading && !branches) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={tx('title')} back />
        <CardSkeletons count={2} lines={3} label={tx('loading')} />
      </div>
    );
  }
  if (!branches) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={tx('title')} back />
        {error ? <InlineError onRetry={() => void refetch()} retrying={isRefetching} /> : <EmptyState icon={MapPin} title={tx('empty')} />}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title={tx('title')} subtitle={tx('subtitle')} back />
      {branches.length === 0 && <EmptyState icon={MapPin} title={tx('empty')} />}
      <ul className="flex flex-col gap-3">
        {branches.map((branch) => (
          <li key={branch.id} className="flex flex-col gap-3">
            <BranchCard branch={branch} editing={editingId === branch.id} onEdit={() => setEditingId(branch.id)} />
            {editingId === branch.id && <BranchEditor key={branch.id} branch={branch} onDone={() => setEditingId(null)} />}
          </li>
        ))}
      </ul>
      <p className="px-1 text-footnote text-fg-muted">{tx('webHint')}</p>
    </div>
  );
}
