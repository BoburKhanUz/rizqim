import React, { useState, useEffect, useRef } from 'react';
import { Users, UserPlus, Trash2, Shield, Share2, Copy, Check } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert, showConfirm } from '../lib/dialogs';
import { fetchEmployees as loadEmployees, getApiErrorMessage, getApiFieldError, inviteEmployee, removeEmployee, transferEmployee, type Employee } from '@rizqim/staff';
import { staffApi } from '../services/staffApi';
import { useAuthStore } from '../store/authStore';
import { logApiError } from '../lib/apiError';
import { Button, Card, CardSkeletons, EmptyState, Field, IconButton, InlineError, PageHeader, Section, fieldClass } from '../components/ui';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import type { Branch } from '../types';

const NO_BRANCHES: Branch[] = [];

// ` • <branch>` after the role. "Filialsiz" is for an employee whose branch is null; a response that did not
// load the branch (undefined) says nothing about it, so nothing is shown rather than a wrong "no branch".
function branchSuffix(employee: Employee): string {
  const name = employee.branch?.name;
  if (name) return ` • ${name}`;
  return employee.branch === null ? ' • Filialsiz' : '';
}

export default function OwnerEmployees() {
  const { store } = useAuthStore();
  const branches = store?.branches ?? NO_BRANCHES;

  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [inviteLink, setInviteLink] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const copiedTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const [newRole, setNewRole] = useState<'manager' | 'courier'>('manager');
  const [newName, setNewName] = useState('');
  const [chosenBranchId, setChosenBranchId] = useState<string>('');
  // Until the owner picks one, the first branch is selected
  const selectedBranchId = chosenBranchId || (branches[0] ? branches[0].id.toString() : '');
  // Bumped to load the list again after an invite, a transfer or a removal
  const [reloadCount, setReloadCount] = useState(0);
  const refreshEmployees = () => setReloadCount((count) => count + 1);

  // The "Nusxalandi" timer must not outlive the screen
  useEffect(() => () => clearTimeout(copiedTimer.current), []);

  useEffect(() => {
    let cancelled = false;
    const fetchEmployees = async () => {
      try {
        const list = await loadEmployees(staffApi);
        if (!cancelled) {
          setEmployees(list);
          setLoadFailed(false);
        }
      } catch (err) {
        logApiError('Failed to load employees:', err);
        if (!cancelled) setLoadFailed(true);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchEmployees();
    return () => {
      cancelled = true;
    };
  }, [reloadCount]);

  const handleGenerateInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim()) return;

    hapticFeedback('medium');
    setGenerating(true);
    try {
      const invite = await inviteEmployee(staffApi, {
        role: newRole,
        name: newName,
        branchId: selectedBranchId ? parseInt(selectedBranchId) : null,
      });
      setInviteLink(invite.invite_link);
      refreshEmployees(); // Refresh list to show pending employee
      setNewName('');
    } catch (err) {
      void showAlert(getApiErrorMessage(err) || 'Xatolik yuz berdi');
    } finally {
      setGenerating(false);
    }
  };

  const handleDelete = async (employee: Employee) => {
    const confirmed = await showConfirm(`${employee.name} xodimini o'chirmoqchimisiz?`, {
      title: "Xodimni o'chirish",
      confirmText: "O'chirish",
      destructive: true,
    });
    if (!confirmed) return;
    hapticFeedback('heavy');
    try {
      await removeEmployee(staffApi, employee.id);
      refreshEmployees();
    } catch {
      void showAlert("O'chirishda xatolik yuz berdi");
    }
  };

  const handleTransferBranch = async (employeeId: number, branchIdStr: string) => {
    // The backend only moves an employee to another branch (branch_id is required)
    if (!branchIdStr) return;
    hapticFeedback('medium');
    try {
      await transferEmployee(staffApi, employeeId, parseInt(branchIdStr));
      refreshEmployees();
    } catch (err) {
      void showAlert(getApiFieldError(err, 'branch_id') || getApiErrorMessage(err) || 'Filialni o\'zgartirishda xatolik yuz berdi');
    }
  };

  const copyLink = () => {
    if (inviteLink) {
      navigator.clipboard.writeText(inviteLink);
      setCopied(true);
      notificationOccurred('success');
      clearTimeout(copiedTimer.current);
      copiedTimer.current = setTimeout(() => setCopied(false), 2000);
    }
  };

  const shareLink = () => {
    if (inviteLink && window.Telegram?.WebApp) {
      window.Telegram.WebApp.openTelegramLink(
        `https://t.me/share/url?url=${encodeURIComponent(inviteLink)}&text=${encodeURIComponent('Siz jamoamizga taklif qilindingiz! Ushbu link orqali tizimga kiring.')}`
      );
    }
  };

  const subtitle = loading || loadFailed ? undefined : `Jami ${employees.length} ta xodim`;

  if (loading) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title="Xodimlar" />
        <CardSkeletons count={3} lines={2} label="Xodimlar yuklanmoqda" />
      </div>
    );
  }

  if (loadFailed && employees.length === 0) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title="Xodimlar" />
        <InlineError
          onRetry={() => {
            setLoading(true);
            refreshEmployees();
          }}
        />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-5 px-4 pt-2 animate-fade-in">
      <PageHeader title="Xodimlar" subtitle={subtitle} />

      <Card className="flex flex-col gap-4">
        <h2 className="flex items-center gap-2 text-headline font-bold text-fg">
          <span className="flex size-8 items-center justify-center rounded-control bg-brand-soft text-brand-soft-fg">
            <UserPlus aria-hidden="true" className="size-5" />
          </span>
          Yangi xodim qo'shish
        </h2>

        {!inviteLink ? (
          <form onSubmit={handleGenerateInvite} className="flex flex-col gap-4">
            <SegmentedControl
              label="Lavozim"
              segments={[
                { value: 'manager', label: 'Menejer' },
                { value: 'courier', label: 'Kuryer' },
              ]}
              value={newRole}
              onChange={setNewRole}
            />

            <Field label="Ism-familiya">
              {(props) => (
                <input
                  {...props}
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  placeholder="Xodimning ism-familiyasi"
                  className={fieldClass}
                  required
                />
              )}
            </Field>

            {branches.length > 0 && (
              <Field label="Filialga biriktirish">
                {(props) => (
                  <select {...props} value={selectedBranchId} onChange={(e) => setChosenBranchId(e.target.value)} className={fieldClass}>
                    {branches.map((b) => (
                      <option key={b.id} value={b.id}>
                        {b.name}
                      </option>
                    ))}
                  </select>
                )}
              </Field>
            )}

            <Button type="submit" size="lg" loading={generating} fullWidth>
              Taklifnoma yaratish
            </Button>
          </form>
        ) : (
          <div className="flex flex-col gap-3 rounded-card bg-brand-soft p-4 text-brand-soft-fg animate-fade-in">
            <p className="text-body">
              Xodimga quyidagi havolani yuboring. U bosganda avtomatik ravishda ro'yxatdan o'tadi.
            </p>
            <div className="flex gap-2">
              <Button variant="secondary" icon={copied ? Check : Copy} onClick={copyLink} className="flex-1">
                {copied ? 'Nusxalandi' : 'Nusxa olish'}
              </Button>
              <Button icon={Share2} onClick={shareLink} className="flex-1">
                Ulashish
              </Button>
            </div>
            <Button variant="ghost" onClick={() => setInviteLink(null)}>
              Yangi taklifnoma yaratish
            </Button>
          </div>
        )}
      </Card>

      <Section title="Barcha xodimlar">
        {employees.length === 0 ? (
          <EmptyState icon={Users} title="Hozircha xodimlar yo'q" text="Yuqoridan taklifnoma yaratib, xodim qo'shing." />
        ) : (
          <ul className="flex flex-col gap-3">
            {employees.map((emp) => (
              <li key={emp.id}>
                <Card className="flex flex-col gap-3 p-3">
                  <div className="flex items-center gap-3">
                    <span
                      className={`flex size-11 shrink-0 items-center justify-center rounded-control ${emp.role === 'manager' ? 'bg-brand-soft text-brand-soft-fg' : 'bg-info-soft text-info-soft-fg'}`}
                    >
                      <Shield aria-hidden="true" className="size-5" />
                    </span>

                    <div className="min-w-0 flex-1">
                      <h3 className="truncate text-body font-bold text-fg">{emp.name}</h3>
                      <p className="text-footnote text-fg-muted">
                        {emp.role === 'manager' ? 'Menejer' : 'Kuryer'}
                        {branchSuffix(emp)}
                        {emp.telegram_invite_token ? ' • (Kutmoqda)' : ''}
                      </p>
                    </div>

                    <IconButton icon={Trash2} variant="danger" label={`${emp.name} xodimini o'chirish`} onClick={() => handleDelete(emp)} />
                  </div>

                  {branches.length > 0 && (
                    <Field label="Filialni o'zgartirish" className="border-t border-line pt-3">
                      {(props) => (
                        <select {...props} value={emp.branch_id || ''} onChange={(e) => handleTransferBranch(emp.id, e.target.value)} className={fieldClass}>
                          <option value="" disabled>Filialsiz</option>
                          {branches.map((b) => (
                            <option key={b.id} value={b.id}>
                              {b.name}
                            </option>
                          ))}
                        </select>
                      )}
                    </Field>
                  )}
                </Card>
              </li>
            ))}
          </ul>
        )}
      </Section>
    </div>
  );
}
