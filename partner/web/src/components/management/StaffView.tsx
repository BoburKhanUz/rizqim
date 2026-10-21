'use client';

import { useMemo, useState } from 'react';
import { KeyRound, Pencil, Search, ShieldCheck, Trash2, UserCog, UserPlus, Users } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, IconButton, InlineError, cx, fieldClass } from '@rizqim/ui';
import { useMenuBranches, useManagedStaff, useRemoveManagedStaff, type CreatedStaff, type StaffMember } from '@rizqim/staff';
import { roleLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { PageHeader } from '../PageHeader';
import { useCan } from './access';
import { ConfirmDialog } from './ConfirmDialog';
import { errorText } from './errors';
import { InviteDialog } from './InviteDialog';
import { Notice } from './Notice';
import { Pill } from './Pill';
import { StaffFormDialog, ASSIGNABLE_ROLES } from './StaffFormDialog';
import { StaffPinDialog } from './StaffPinDialog';
import { StaffRoleDialog } from './StaffRoleDialog';

type Dialog =
  | { kind: 'add' }
  | { kind: 'edit'; member: StaffMember }
  | { kind: 'role'; member: StaffMember }
  | { kind: 'pin'; member: StaffMember }
  | { kind: 'remove'; member: StaffMember }
  | { kind: 'invite'; created: CreatedStaff };

// GET /owner/staff: the staff the signed-in person manages, with role, branch, the login phone and the PIN. What each
// person may do with a member comes from the server (`can_edit`) and from /me/permissions (role and PIN need staff.manage /
// staff.pin); the backend still checks every request.
export function StaffView() {
  const { t } = useI18n();
  const staff = useManagedStaff();
  const branches = useMenuBranches();
  const access = useCan();
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const remove = useRemoveManagedStaff();
  const [removeError, setRemoveError] = useState<string | null>(null);

  const members = useMemo(() => {
    const needle = search.trim().toLowerCase();
    const digits = needle.replace(/\D/g, '');
    return (staff.data ?? []).filter((member) => {
      if (roleFilter && member.role !== roleFilter) return false;
      if (!needle) return true;
      return member.name.toLowerCase().includes(needle) || (digits !== '' && (member.phone ?? '').replace(/\D/g, '').includes(digits)) || member.staff_code === needle;
    });
  }, [staff.data, search, roleFilter]);

  const close = () => {
    setDialog(null);
    setRemoveError(null);
  };
  const done = (message: string) => {
    setNotice(message);
    close();
  };

  const actions = {
    canManageRole: access.can('staff.manage'),
    canPin: access.can('staff.pin'),
    onEdit: (member: StaffMember) => setDialog({ kind: 'edit', member }),
    onRole: (member: StaffMember) => setDialog({ kind: 'role', member }),
    onPin: (member: StaffMember) => setDialog({ kind: 'pin', member }),
    onRemove: (member: StaffMember) => setDialog({ kind: 'remove', member }),
  };

  return (
    <>
      <PageHeader
        title={t('nav_staff')}
        subtitle={t('staff_subtitle')}
        action={
          <Button icon={UserPlus} onClick={() => setDialog({ kind: 'add' })} disabled={!staff.isSuccess}>
            {t('staff_add')}
          </Button>
        }
      />
      <Notice message={notice} />

      {staff.isPending ? (
        <CardSkeletons count={4} lines={2} label={t('loading')} />
      ) : staff.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(staff.error, t)} retryLabel={t('retry')} retrying={staff.isFetching} onRetry={() => void staff.refetch()} />
      ) : staff.data.length === 0 ? (
        <EmptyState icon={Users} title={t('staff_empty')} text={t('staff_emptyText')} action={<Button icon={UserPlus} onClick={() => setDialog({ kind: 'add' })}>{t('staff_add')}</Button>} />
      ) : (
        <>
          <div className="mb-4 flex flex-wrap gap-3">
            <label className="relative min-w-52 flex-1">
              <span className="sr-only">{t('staff_search')}</span>
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-subtle" />
              <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('staff_search')} className={cx(fieldClass, 'pl-9')} />
            </label>
            <label>
              <span className="sr-only">{t('staff_role')}</span>
              <select value={roleFilter} onChange={(event) => setRoleFilter(event.target.value)} className={cx(fieldClass, 'w-auto min-w-44')}>
                <option value="">{t('staff_allRoles')}</option>
                {ASSIGNABLE_ROLES.map((value) => (
                  <option key={value} value={value}>
                    {roleLabel(t, value)}
                  </option>
                ))}
              </select>
            </label>
          </div>
          {members.length === 0 ? (
            <EmptyState icon={Search} title={t('staff_noMatch')} text={t('staff_noMatchText')} />
          ) : (
            <>
              <StaffTable members={members} {...actions} />
              <StaffCards members={members} {...actions} />
            </>
          )}
        </>
      )}

      {(dialog?.kind === 'add' || dialog?.kind === 'edit') && (
        <StaffFormDialog
          member={dialog.kind === 'edit' ? dialog.member : null}
          branches={branches.data ?? []}
          onClose={close}
          onCreated={(created) => {
            setNotice(t('staff_added', { name: created.staff.name }));
            setDialog({ kind: 'invite', created });
          }}
          onSaved={(member) => done(t('staff_saved', { name: member.name }))}
        />
      )}
      {dialog?.kind === 'invite' && <InviteDialog name={dialog.created.staff.name} link={dialog.created.invite_link} phoneSet={dialog.created.staff.login_phone_enabled} onClose={close} />}
      {dialog?.kind === 'role' && <StaffRoleDialog member={dialog.member} branches={branches.data ?? []} onClose={close} onSaved={() => done(t('staff_roleSaved', { name: dialog.member.name }))} />}
      {dialog?.kind === 'pin' && <StaffPinDialog member={dialog.member} onClose={close} onChanged={done} />}
      {dialog?.kind === 'remove' && (
        <ConfirmDialog
          title={t('staff_removeTitle', { name: dialog.member.name })}
          text={t('staff_removeText')}
          confirmLabel={t('staff_remove')}
          busy={remove.isPending}
          error={removeError}
          onClose={close}
          onConfirm={() => {
            setRemoveError(null);
            remove.mutate(dialog.member.id, { onSuccess: () => done(t('staff_removed', { name: dialog.member.name })), onError: (err) => setRemoveError(errorText(err, t)) });
          }}
        />
      )}
    </>
  );
}

interface RowActions {
  canManageRole: boolean;
  canPin: boolean;
  onEdit: (member: StaffMember) => void;
  onRole: (member: StaffMember) => void;
  onPin: (member: StaffMember) => void;
  onRemove: (member: StaffMember) => void;
}

// Only the buttons this person may use on this member
function MemberActions({ member, canManageRole, canPin, onEdit, onRole, onPin, onRemove }: RowActions & { member: StaffMember }) {
  const { t } = useI18n();
  if (!member.can_edit) return <span className="text-footnote text-fg-subtle">{t('staff_readOnly')}</span>;
  return (
    <div className="flex flex-wrap justify-end gap-1">
      <IconButton icon={Pencil} label={t('staff_editAria', { name: member.name })} onClick={() => onEdit(member)} />
      {canManageRole && <IconButton icon={UserCog} label={t('staff_roleAria', { name: member.name })} onClick={() => onRole(member)} />}
      {canPin && <IconButton icon={KeyRound} label={t('staff_pinAria', { name: member.name })} onClick={() => onPin(member)} />}
      {canManageRole && <IconButton icon={Trash2} variant="danger" label={t('staff_removeAria', { name: member.name })} onClick={() => onRemove(member)} />}
    </div>
  );
}

// The login phone, in words: whether the person signs in by an SMS code to it
function LoginPhone({ member }: { member: StaffMember }) {
  const { t } = useI18n();
  if (member.login_phone_enabled) {
    return (
      <div className="flex flex-col items-start gap-1">
        <span className="text-fg">{member.phone}</span>
        <Pill tone="success" icon={ShieldCheck}>
          {t('staff_smsLogin')}
        </Pill>
      </div>
    );
  }
  return <Pill tone="neutral">{t('staff_noLoginPhone')}</Pill>;
}

function AccessPills({ member }: { member: StaffMember }) {
  const { t } = useI18n();
  return (
    <div className="flex flex-wrap gap-1.5">
      <Pill tone={member.has_pin ? 'success' : 'neutral'} icon={KeyRound}>
        {member.has_pin ? t('staff_hasPin') : t('staff_noPin')}
      </Pill>
      {member.invite_pending ? <Pill tone="warning">{t('staff_invitePending')}</Pill> : member.telegram_linked ? <Pill tone="info">{t('staff_telegram')}</Pill> : null}
    </div>
  );
}

function StaffTable({ members, ...actions }: { members: StaffMember[] } & RowActions) {
  const { t } = useI18n();
  return (
    <Card padded={false} className="hidden overflow-x-auto md:block">
      <table className="w-full text-left text-body">
        <thead>
          <tr className="border-b border-line text-footnote text-fg-muted">
            <th scope="col" className="px-4 py-3 font-bold">{t('staff_name')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('staff_role')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('staff_branch')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('staff_loginPhone')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('staff_access')}</th>
            <th scope="col" className="px-4 py-3 text-right font-bold">{t('mgmt_actions')}</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {members.map((member) => (
            <tr key={member.id}>
              <th scope="row" className="px-4 py-3 text-left font-bold text-fg">
                {member.name}
                {member.staff_code && <span className="block text-caption font-normal text-fg-muted">{t('staff_code')}: {member.staff_code}</span>}
              </th>
              <td className="px-4 py-3 text-fg">{roleLabel(t, member.role)}</td>
              <td className="px-4 py-3 text-fg">{member.branch?.name ?? <span className="text-fg-muted">{t('staff_wholeStore')}</span>}</td>
              <td className="px-4 py-3">
                <LoginPhone member={member} />
              </td>
              <td className="px-4 py-3">
                <AccessPills member={member} />
              </td>
              <td className="px-4 py-3">
                <MemberActions member={member} {...actions} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}

function StaffCards({ members, ...actions }: { members: StaffMember[] } & RowActions) {
  const { t } = useI18n();
  return (
    <ul className="flex flex-col gap-3 md:hidden">
      {members.map((member) => (
        <li key={member.id}>
          <Card className="flex flex-col gap-3">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="font-bold text-fg">{member.name}</p>
                <p className="text-footnote text-fg-muted">
                  {roleLabel(t, member.role)} · {member.branch?.name ?? t('staff_wholeStore')}
                </p>
              </div>
              {member.staff_code && <span className="text-caption text-fg-muted">{t('staff_code')}: {member.staff_code}</span>}
            </div>
            <LoginPhone member={member} />
            <AccessPills member={member} />
            <MemberActions member={member} {...actions} />
          </Card>
        </li>
      ))}
    </ul>
  );
}
