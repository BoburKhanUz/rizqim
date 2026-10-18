import React, { useState, useEffect } from 'react';
import { Users, UserPlus, Trash2, Shield, Share2, Copy, Check, MapPin } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { FullScreenLoader } from '../components/Feedback';
import type { ApiResponse, Branch, Employee, EmployeeInvite } from '../types';

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
  const [generating, setGenerating] = useState(false);
  const [inviteLink, setInviteLink] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [newRole, setNewRole] = useState<'manager' | 'courier'>('manager');
  const [newName, setNewName] = useState('');
  const [chosenBranchId, setChosenBranchId] = useState<string>('');
  // Until the owner picks one, the first branch is selected
  const selectedBranchId = chosenBranchId || (branches[0] ? branches[0].id.toString() : '');
  // Bumped to load the list again after an invite, a transfer or a removal
  const [reloadCount, setReloadCount] = useState(0);
  const refreshEmployees = () => setReloadCount((count) => count + 1);

  useEffect(() => {
    let cancelled = false;
    const fetchEmployees = async () => {
      try {
        const res = await api.get<ApiResponse<Employee[]>>('/owner/employees');
        if (res.data.success && !cancelled) {
          setEmployees(res.data.data);
        }
      } catch (err) {
        logApiError('Failed to load employees:', err);
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
      const res = await api.post<ApiResponse<EmployeeInvite>>('/owner/employees/invite', {
        role: newRole,
        name: newName,
        branch_id: selectedBranchId ? parseInt(selectedBranchId) : null,
      });
      if (res.data.success) {
        setInviteLink(res.data.data.invite_link);
        refreshEmployees(); // Refresh list to show pending employee
        setNewName('');
      }
    } catch (err) {
      alert(getApiErrorMessage(err) || 'Xatolik yuz berdi');
    } finally {
      setGenerating(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm("Rostdan ham bu xodimni o'chirmoqchimisiz?")) return;
    hapticFeedback('heavy');
    try {
      await api.delete(`/owner/employees/${id}`);
      refreshEmployees();
    } catch {
      alert('O\'chirishda xatolik yuz berdi');
    }
  };

  const handleTransferBranch = async (employeeId: number, branchIdStr: string) => {
    // The backend only moves an employee to another branch (branch_id is required)
    if (!branchIdStr) return;
    hapticFeedback('medium');
    try {
      const res = await api.post<ApiResponse<Employee>>(`/owner/employees/${employeeId}/transfer`, {
        branch_id: parseInt(branchIdStr),
      });
      if (res.data.success) {
        refreshEmployees();
      }
    } catch (err) {
      alert(getApiFieldError(err, 'branch_id') || getApiErrorMessage(err) || 'Filialni o\'zgartirishda xatolik yuz berdi');
    }
  };

  const copyLink = () => {
    if (inviteLink) {
      navigator.clipboard.writeText(inviteLink);
      setCopied(true);
      hapticFeedback('success');
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const shareLink = () => {
    if (inviteLink && window.Telegram?.WebApp) {
      window.Telegram.WebApp.openTelegramLink(
        `https://t.me/share/url?url=${encodeURIComponent(inviteLink)}&text=${encodeURIComponent('Siz jamoamizga taklif qilindingiz! Ushbu link orqali tizimga kiring.')}`
      );
    }
  };

  if (loading) return <FullScreenLoader message="Xodimlar yuklanmoqda..." />;

  return (
    <div className="p-4 pb-24 animate-fade-in min-h-screen bg-gradient-to-br from-bg via-bg to-primary/5">
      <div className="mb-6 pt-2">
        <h1 className="text-3xl font-black text-text">
          Xodimlar
        </h1>
        <p className="text-xs text-hint font-medium mt-1">Jami {employees.length} ta xodim</p>
      </div>

      <div className="relative mb-8">
        <div className="absolute -inset-0.5 bg-gradient-to-br from-primary/10 to-accent/10 rounded-[28px] blur opacity-50"></div>
        <div className="relative bg-secondary/80 backdrop-blur-xl p-5 rounded-[24px] border border-white/5 shadow-sm space-y-4">
          <div className="flex items-center gap-2 text-primary mb-2">
            <div className="p-1.5 bg-primary/10 rounded-lg">
              <UserPlus className="w-5 h-5" />
            </div>
            <h2 className="font-extrabold text-sm uppercase tracking-wide">Yangi xodim qo'shish</h2>
          </div>

          {!inviteLink ? (
            <form onSubmit={handleGenerateInvite} className="space-y-4">
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => setNewRole('manager')}
                  className={`flex-1 py-2.5 rounded-xl text-xs font-bold transition-all border ${
                    newRole === 'manager' 
                      ? 'bg-primary text-white border-primary shadow-md shadow-primary/20' 
                      : 'bg-bg text-text border-white/5 active:bg-primary/10'
                  }`}
                >
                  Menejer
                </button>
                <button
                  type="button"
                  onClick={() => setNewRole('courier')}
                  className={`flex-1 py-2.5 rounded-xl text-xs font-bold transition-all border ${
                    newRole === 'courier' 
                      ? 'bg-primary text-white border-primary shadow-md shadow-primary/20' 
                      : 'bg-bg text-text border-white/5 active:bg-primary/10'
                  }`}
                >
                  Kuryer
                </button>
              </div>

              <input
                type="text"
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                placeholder="Xodimning ism-familiyasi"
                className="w-full bg-bg/80 backdrop-blur-sm border border-white/5 rounded-2xl px-4 py-3.5 text-sm focus:border-primary/50 focus:bg-secondary outline-none font-bold shadow-inner transition-all placeholder:text-hint/40"
                required
              />

              {branches.length > 0 && (
                <div className="space-y-1.5">
                  <label className="text-[10px] text-hint font-black uppercase tracking-wider pl-1">Filial Biriktirish</label>
                  <select
                    value={selectedBranchId}
                    onChange={(e) => setChosenBranchId(e.target.value)}
                    className="w-full bg-bg/80 backdrop-blur-sm border border-white/5 rounded-2xl px-4 py-3.5 text-sm focus:border-primary/50 focus:bg-secondary outline-none font-bold shadow-inner transition-all text-text"
                  >
                    {branches.map((b) => (
                      <option key={b.id} value={b.id}>
                        {b.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <button
                type="submit"
                disabled={generating}
                className="w-full py-3.5 bg-primary text-white font-bold rounded-2xl flex items-center justify-center gap-2 active:scale-95 transition-transform"
              >
                {generating ? <span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> : 'Taklifnoma Yaratish'}
              </button>
            </form>
          ) : (
            <div className="bg-primary/10 border border-primary/20 rounded-2xl p-4 text-center animate-fade-in">
              <p className="text-xs text-text font-medium mb-3">
                Xodimga quyidagi havolani yuboring. U bosganda avtomatik ravishda ro'yxatdan o'tadi.
              </p>
              
              <div className="flex gap-2 mb-3">
                <button
                  onClick={copyLink}
                  className="flex-1 py-3 bg-bg text-text border border-white/5 rounded-xl font-bold text-xs flex items-center justify-center gap-2 active:bg-primary/10"
                >
                  {copied ? <Check className="w-4 h-4 text-green-500" /> : <Copy className="w-4 h-4" />}
                  {copied ? 'Nusxalandi' : 'Nusxa olish'}
                </button>
                <button
                  onClick={shareLink}
                  className="flex-1 py-3 bg-primary text-white rounded-xl font-bold text-xs flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-md shadow-primary/20"
                >
                  <Share2 className="w-4 h-4" />
                  Ulashish
                </button>
              </div>

              <button
                onClick={() => setInviteLink(null)}
                className="text-[10px] text-hint font-bold uppercase underline underline-offset-2"
              >
                Yangi yaratish
              </button>
            </div>
          )}
        </div>
      </div>

      <div className="space-y-3">
        <h2 className="text-sm font-black text-text-h mb-3 uppercase tracking-wider pl-1">Barcha Xodimlar</h2>
        
        {employees.length === 0 ? (
          <div className="text-center py-10">
            <Users className="w-12 h-12 text-hint/30 mx-auto mb-3" />
            <p className="text-sm text-hint font-medium">Hozircha xodimlar yo'q</p>
          </div>
        ) : (
          employees.map(emp => (
            <div key={emp.id} className="bg-secondary/80 backdrop-blur-md rounded-[20px] p-3 border border-white/5 flex flex-col gap-2">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 rounded-2xl bg-bg/50 border border-white/5 flex items-center justify-center shrink-0">
                  <Shield className={`w-5 h-5 ${emp.role === 'manager' ? 'text-amber-500' : 'text-blue-500'}`} />
                </div>
                
                <div className="flex-1 min-w-0">
                  <h3 className="font-bold text-sm text-text-h truncate">{emp.name}</h3>
                  <p className="text-[10px] text-hint mt-0.5">
                    {emp.role === 'manager' ? 'Menejer' : 'Kuryer'}
                    {branchSuffix(emp)}
                    {emp.telegram_invite_token ? ' • (Kutmoqda)' : ''}
                  </p>
                </div>

                <button
                  onClick={() => handleDelete(emp.id)}
                  className="w-10 h-10 flex items-center justify-center shrink-0 bg-red-500/10 text-red-500 rounded-xl active:scale-90 transition-transform hover:bg-red-500 hover:text-white"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>

              {branches.length > 0 && (
                <div className="flex items-center gap-2 border-t border-white/5 pt-2 mt-1">
                  <MapPin className="w-3.5 h-3.5 text-hint/50" />
                  <span className="text-[10px] text-hint font-bold uppercase tracking-wider">Filialni o'zgartirish:</span>
                  <select
                    value={emp.branch_id || ''}
                    onChange={(e) => handleTransferBranch(emp.id, e.target.value)}
                    className="flex-1 text-[11px] bg-bg/50 border border-white/5 rounded-lg px-2 py-1 outline-none text-text font-bold"
                  >
                    <option value="" disabled>Filialsiz</option>
                    {branches.map((b) => (
                      <option key={b.id} value={b.id}>
                        {b.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}
            </div>
          ))
        )}
      </div>
    </div>
  );
}
