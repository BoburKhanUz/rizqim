'use client';

import { useEffect, useRef, useState } from 'react';
import { KeyRound, Link2, Pause, Play, ShieldAlert, UserPlus, UserCog, Users } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, EmptyState, InlineError, Notice, PageHeader, Pill } from '@rizqim/ui';
import { useInviteMember, useReissueInvite, useResetTwoFactor, useTeam, useUpdateMember } from '@/lib/api/hooks';
import { isInvited, type InviteLink, type PlatformRole, type TeamMember } from '@/lib/api/types';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { roleLabel } from '@/lib/labels';
import { useCan, useMe } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { InviteDialog } from './InviteDialog';
import { InviteLinkDialog } from './InviteLinkDialog';
import { RoleDialog } from './RoleDialog';

type Dialog =
  | { kind: 'invite' }
  | { kind: 'role'; member: TeamMember }
  | { kind: 'disable'; member: TeamMember }
  | { kind: 'enable'; member: TeamMember }
  | { kind: 'reset'; member: TeamMember }
  | { kind: 'reissue'; member: TeamMember }
  | { kind: 'link'; name: string; link: InviteLink };

// Tizim → Jamoa (team.manage): who works for RIZQIM, their role, whether 2FA is on, the last sign-in. Every change is one call the
// backend audits and asks a fresh 2FA code for (the step-up dialog opens by itself).
export function TeamView({ focusId = null }: { focusId?: number | null }) {
  const t = useT();
  const { ready, can } = useCan();
  const me = useMe();
  const allowed = ready && can('team.manage');
  const team = useTeam(allowed);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const invite = useInviteMember();
  const reissue = useReissueInvite();
  const update = useUpdateMember();
  const reset = useResetTwoFactor();

  const close = () => {
    setDialog(null);
    invite.reset();
    reissue.reset();
    update.reset();
    reset.reset();
  };

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_team')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const selfId = me?.member.id ?? null;
  const done = (message: string) => {
    close();
    setNotice(message);
  };

  return (
    <>
      <PageHeader
        title={t('team_title')}
        subtitle={t('team_subtitle')}
        action={
          <Button icon={UserPlus} onClick={() => { setNotice(null); setDialog({ kind: 'invite' }); }}>
            {t('team_invite')}
          </Button>
        }
      />
      <Notice message={notice} />
      {team.isError ? (
        <InlineError title={t('team_loadError')} message={errorText(team.error, t)} retryLabel={t('retry')} retrying={team.isFetching} onRetry={() => void team.refetch()} />
      ) : !team.isSuccess ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : team.data.length === 0 ? (
        <EmptyState icon={Users} title={t('team_empty')} />
      ) : (
        <ul aria-label={t('team_title')} className="flex max-w-4xl flex-col gap-3">
          {team.data.map((member) => (
            <MemberCard
              key={member.id}
              member={member}
              isSelf={member.id === selfId}
              focused={member.id === focusId}
              onAction={(kind) => {
                setNotice(null);
                setDialog({ kind, member });
              }}
            />
          ))}
        </ul>
      )}

      {dialog?.kind === 'invite' && (
        <InviteDialog
          busy={invite.isPending}
          error={invite.error ? errorText(invite.error, t) : null}
          fieldErrors={invite.error}
          onClose={close}
          onSubmit={(input) =>
            invite.mutate(input, {
              onSuccess: (result) => {
                close();
                setDialog({ kind: 'link', name: input.name, link: result });
              },
            })
          }
        />
      )}

      {dialog?.kind === 'role' && (
        <RoleDialog
          member={dialog.member}
          busy={update.isPending}
          error={update.error ? errorText(update.error, t) : null}
          onClose={close}
          onSubmit={(role: PlatformRole) =>
            update.mutate({ id: dialog.member.id, change: { platform_role: role } }, { onSuccess: () => done(t('team_roleSaved', { name: dialog.member.name, role: roleLabel(role) })) })
          }
        />
      )}

      {dialog?.kind === 'disable' && (
        <ConfirmDialog
          title={t('team_disableTitle', { name: dialog.member.name })}
          text={t('team_disableText')}
          confirmLabel={t('team_disableConfirm')}
          cancelLabel={t('cancel')}
          closeLabel={t('close')}
          busy={update.isPending}
          error={update.error ? errorText(update.error, t) : null}
          onClose={close}
          onConfirm={() => update.mutate({ id: dialog.member.id, change: { disabled: true } }, { onSuccess: () => done(t('team_disabledDone', { name: dialog.member.name })) })}
        />
      )}

      {dialog?.kind === 'enable' && (
        <ConfirmDialog
          tone="primary"
          title={t('team_enableTitle', { name: dialog.member.name })}
          text={t('team_enableText')}
          confirmLabel={t('team_enableConfirm')}
          cancelLabel={t('cancel')}
          closeLabel={t('close')}
          busy={update.isPending}
          error={update.error ? errorText(update.error, t) : null}
          onClose={close}
          onConfirm={() => update.mutate({ id: dialog.member.id, change: { disabled: false } }, { onSuccess: () => done(t('team_enabledDone', { name: dialog.member.name })) })}
        />
      )}

      {dialog?.kind === 'reset' && (
        <ConfirmDialog
          title={t('team_resetTitle', { name: dialog.member.name })}
          text={t('team_resetText')}
          confirmLabel={t('team_resetConfirm')}
          cancelLabel={t('cancel')}
          closeLabel={t('close')}
          busy={reset.isPending}
          error={reset.error ? errorText(reset.error, t) : null}
          onClose={close}
          onConfirm={() => reset.mutate(dialog.member.id, { onSuccess: () => done(t('team_resetDone', { name: dialog.member.name })) })}
        />
      )}

      {dialog?.kind === 'reissue' && (
        <ConfirmDialog
          tone="primary"
          title={t('team_reissueTitle', { name: dialog.member.name })}
          text={t('team_reissueText')}
          confirmLabel={t('team_reissueConfirm')}
          cancelLabel={t('cancel')}
          closeLabel={t('close')}
          busy={reissue.isPending}
          error={reissue.error ? errorText(reissue.error, t) : null}
          onClose={close}
          onConfirm={() =>
            reissue.mutate(dialog.member.id, {
              onSuccess: (link) => {
                close();
                setDialog({ kind: 'link', name: dialog.member.name, link });
              },
            })
          }
        />
      )}

      {dialog?.kind === 'link' && <InviteLinkDialog name={dialog.name} link={dialog.link} onClose={close} />}
    </>
  );
}

type Action = 'role' | 'disable' | 'enable' | 'reset' | 'reissue';

function MemberCard({ member, isSelf, focused, onAction }: { member: TeamMember; isSelf: boolean; focused: boolean; onAction: (kind: Action) => void }) {
  const t = useT();
  const ref = useRef<HTMLLIElement>(null);
  useEffect(() => {
    if (focused) ref.current?.scrollIntoView?.({ block: 'center' });
  }, [focused]);

  const label = (text: string) => `${text}: ${member.name}`;
  return (
    <li ref={ref} className="list-none">
      <Card className={focused ? 'flex flex-col gap-3 outline-2 outline-brand' : 'flex flex-col gap-3'}>
        <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
          <div className="min-w-0">
            <h2 className="flex flex-wrap items-center gap-2 text-headline font-bold text-fg">
              <span className="min-w-0 break-words">{member.name}</span>
              {isSelf && <Pill tone="info">{t('team_you')}</Pill>}
            </h2>
            <p className="break-all text-footnote text-fg-muted">{member.email}</p>
          </div>
          <div className="flex flex-wrap items-center gap-1.5">
            <Pill tone="neutral">{roleLabel(member.platform_role)}</Pill>
            {isInvited(member) ? <Pill tone="warning">{t('team_invitePending')}</Pill> : member.disabled ? <Pill tone="danger">{t('team_disabledPill')}</Pill> : <Pill tone="success">{t('team_active')}</Pill>}
            <Pill tone={member.two_factor_enabled ? 'success' : 'warning'}>{member.two_factor_enabled ? t('team_twoFactorOn') : t('team_twoFactorOff')}</Pill>
          </div>
        </div>

        <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-footnote">
          <div>
            <dt className="text-fg-muted">{t('team_lastLogin')}</dt>
            <dd className="text-fg">{when(member.last_login_at, t('never'))}</dd>
          </div>
          <div>
            <dt className="text-fg-muted">{t('team_sessions')}</dt>
            <dd className="text-fg">{member.active_sessions}</dd>
          </div>
        </dl>

        {isSelf ? (
          <p className="text-footnote text-fg-muted">{t('team_selfNote')}</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" icon={UserCog} aria-label={label(t('team_changeRole'))} onClick={() => onAction('role')}>
              {t('team_changeRole')}
            </Button>
            {isInvited(member) ? (
              <Button variant="secondary" icon={Link2} aria-label={label(t('team_reissue'))} onClick={() => onAction('reissue')}>
                {t('team_reissue')}
              </Button>
            ) : (
              <Button variant="secondary" icon={KeyRound} aria-label={label(t('team_resetTwoFactor'))} onClick={() => onAction('reset')}>
                {t('team_resetTwoFactor')}
              </Button>
            )}
            {member.disabled ? (
              <Button variant="secondary" icon={Play} aria-label={label(t('team_enable'))} onClick={() => onAction('enable')}>
                {t('team_enable')}
              </Button>
            ) : (
              <Button variant="secondary" icon={Pause} aria-label={label(t('team_disable'))} onClick={() => onAction('disable')}>
                {t('team_disable')}
              </Button>
            )}
          </div>
        )}
      </Card>
    </li>
  );
}
