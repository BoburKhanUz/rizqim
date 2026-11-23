'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import type { PlatformRole } from '@/lib/api/types';
import { fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { RoleChecklist } from './RoleChecklist';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// A new member: name, email, one or more roles. The answer is a one-time link (InviteLinkDialog).
export function InviteDialog({
  busy,
  error,
  fieldErrors,
  onSubmit,
  onClose,
}: {
  busy: boolean;
  error: string | null;
  // The failed call itself, for its field messages (the email is taken ...)
  fieldErrors: unknown;
  onSubmit: (input: { name: string; email: string; platform_roles: PlatformRole[] }) => void;
  onClose: () => void;
}) {
  const t = useT();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [roles, setRoles] = useState<PlatformRole[]>(['operator']);
  const [rolesError, setRolesError] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  function submit(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    if (!name.trim() || !EMAIL.test(email.trim())) {
      setLocalError(t('team_inviteInvalid'));
      return;
    }
    setLocalError(null);
    if (roles.length === 0) {
      setRolesError(true);
      return;
    }
    setRolesError(false);
    onSubmit({ name: name.trim(), email: email.trim(), platform_roles: roles });
  }

  const emailError = fieldError(fieldErrors, 'email');
  const nameError = fieldError(fieldErrors, 'name');
  return (
    <Dialog open title={t('team_inviteTitle')} busy={busy} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field label={t('team_inviteName')} error={nameError}>
          {(control) => <input {...control} data-autofocus className={fieldClass} type="text" autoComplete="off" value={name} onChange={(event) => setName(event.target.value)} />}
        </Field>
        <Field label={t('team_inviteEmail')} error={emailError}>
          {(control) => <input {...control} className={fieldClass} type="email" autoComplete="off" inputMode="email" value={email} onChange={(event) => setEmail(event.target.value)} />}
        </Field>
        <RoleChecklist
          value={roles}
          onChange={(next) => {
            setRoles(next);
            if (next.length > 0) setRolesError(false);
          }}
          error={rolesError ? t('team_rolesRequired') : fieldError(fieldErrors, 'platform_roles') || null}
          hint={t('team_inviteRoleHint')}
        />
        <FormError message={localError ?? (emailError || nameError ? null : error)} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {t('team_inviteSend')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
