'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import { isPlatformRole, PLATFORM_ROLES, type PlatformRole, type TeamMember } from '@/lib/api/types';
import { ROLE_LABELS } from '@/lib/labels';
import { useT } from '../I18nProvider';

export function RoleDialog({ member, busy, error, onSubmit, onClose }: { member: TeamMember; busy: boolean; error: string | null; onSubmit: (role: PlatformRole) => void; onClose: () => void }) {
  const t = useT();
  const [role, setRole] = useState<PlatformRole>(isPlatformRole(member.platform_role) ? member.platform_role : 'viewer');

  function submit(event: FormEvent) {
    event.preventDefault();
    if (!busy) onSubmit(role);
  }

  return (
    <Dialog open title={t('team_roleTitle')} description={t('team_roleText', { name: member.name })} busy={busy} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        <Field label={t('team_inviteRole')}>
          {(control) => (
            <select {...control} data-autofocus className={fieldClass} value={role} onChange={(event) => setRole(event.target.value as PlatformRole)}>
              {PLATFORM_ROLES.map((key) => (
                <option key={key} value={key}>
                  {ROLE_LABELS[key]}
                </option>
              ))}
            </select>
          )}
        </Field>
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('cancel')}
          </Button>
          <Button type="submit" loading={busy} disabled={role === member.platform_role}>
            {t('save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
