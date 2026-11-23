'use client';

import { useId } from 'react';
import { PLATFORM_ROLES, type PlatformRole } from '@/lib/api/types';
import { ROLE_LABELS } from '@/lib/labels';
import { useT } from '../I18nProvider';

// One checkbox per team role: a member holds one or more roles and may do what any of them may. The set comes back in
// PLATFORM_ROLES order (the backend's), so an unchanged set compares equal.
export function RoleChecklist({
  value,
  onChange,
  error,
  hint,
  disabled = false,
  autoFocus = false,
}: {
  value: readonly PlatformRole[];
  onChange: (roles: PlatformRole[]) => void;
  error?: string | null;
  hint?: string;
  disabled?: boolean;
  autoFocus?: boolean;
}) {
  const t = useT();
  const id = useId();
  const noteId = `${id}-note`;
  const note = error ?? hint;

  const toggle = (role: PlatformRole, on: boolean) => onChange(PLATFORM_ROLES.filter((key) => (key === role ? on : value.includes(key))));

  return (
    <fieldset className="flex flex-col gap-1.5" aria-describedby={note ? noteId : undefined} aria-invalid={error ? true : undefined} disabled={disabled}>
      <legend className="mb-1.5 text-footnote font-bold text-fg-muted">{t('team_inviteRole')}</legend>
      <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
        {PLATFORM_ROLES.map((role, index) => (
          <label key={role} className="flex min-h-11 cursor-pointer items-center gap-3 text-body text-fg">
            <input
              type="checkbox"
              className="size-5 shrink-0 accent-[var(--color-brand)]"
              checked={value.includes(role)}
              data-autofocus={autoFocus && index === 0 ? true : undefined}
              onChange={(event) => toggle(role, event.target.checked)}
            />
            <span className="min-w-0 break-words">{ROLE_LABELS[role]}</span>
          </label>
        ))}
      </div>
      {note && (
        <p id={noteId} role={error ? 'alert' : undefined} className={error ? 'text-footnote font-bold text-danger-soft-fg' : 'text-footnote text-fg-muted'}>
          {note}
        </p>
      )}
    </fieldset>
  );
}
