'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, FormError } from '@rizqim/ui';
import { memberRoles, type PlatformRole, type TeamMember } from '@/lib/api/types';
import { useT } from '../I18nProvider';
import { RoleChecklist } from './RoleChecklist';

// The member's whole set of roles: ticked boxes are what they hold; saving sends the new set as one change.
export function RoleDialog({ member, busy, error, onSubmit, onClose }: { member: TeamMember; busy: boolean; error: string | null; onSubmit: (roles: PlatformRole[]) => void; onClose: () => void }) {
  const t = useT();
  const current = memberRoles(member);
  const [roles, setRoles] = useState<PlatformRole[]>(current);
  const empty = roles.length === 0;
  const unchanged = roles.length === current.length && roles.every((role, index) => role === current[index]);

  function submit(event: FormEvent) {
    event.preventDefault();
    if (!busy && !empty && !unchanged) onSubmit(roles);
  }

  return (
    <Dialog open title={t('team_roleTitle')} description={t('team_roleText', { name: member.name })} busy={busy} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        <RoleChecklist value={roles} onChange={setRoles} autoFocus error={empty ? t('team_rolesRequired') : null} hint={t('team_rolesHint')} />
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('cancel')}
          </Button>
          <Button type="submit" loading={busy} disabled={empty || unchanged}>
            {t('save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
