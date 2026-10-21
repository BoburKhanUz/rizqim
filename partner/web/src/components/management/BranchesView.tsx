'use client';

import { useState } from 'react';
import { Clock, MapPin, Pencil, Phone, Plus, Store, Trash2 } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, IconButton, InlineError } from '@rizqim/ui';
import { useDeleteBranch, useMenuBranches, useUpdateBranch, type ManagedBranch } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { PageHeader } from '../PageHeader';
import { useCan } from './access';
import { BranchFormDialog } from './BranchFormDialog';
import { ConfirmDialog } from './ConfirmDialog';
import { errorText, reasonOf } from './errors';
import { hoursText } from './hours';
import { Notice } from './Notice';
import { Pill, type PillTone } from './Pill';

type Dialog = { kind: 'form'; branch: ManagedBranch | null } | { kind: 'delete'; branch: ManagedBranch };

const STATUS_TONE: Record<string, PillTone> = { active: 'success', inactive: 'neutral', temporarily_closed: 'warning' };

// GET /owner/branches: the branches with their working hours and whether the delivery check takes them as open right now
// (`is_open_now`: the server's answer, not a guess from the clock). Create and delete need settings.manage (the owner), editing
// branches.manage (the owner; a manager only their own branch).
export function BranchesView() {
  const { t } = useI18n();
  const branches = useMenuBranches();
  const access = useCan();
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const remove = useDeleteBranch();
  const deactivate = useUpdateBranch();
  const [deleteError, setDeleteError] = useState<{ message: string; inUse: boolean } | null>(null);

  const canCreate = access.can('settings.manage');
  const canEdit = access.can('branches.manage');
  const close = () => {
    setDialog(null);
    setDeleteError(null);
  };

  return (
    <>
      <PageHeader
        title={t('nav_branches')}
        subtitle={t('branches_subtitle')}
        action={
          canCreate && (
            <Button icon={Plus} onClick={() => setDialog({ kind: 'form', branch: null })} disabled={!branches.isSuccess}>
              {t('branches_add')}
            </Button>
          )
        }
      />
      <Notice message={notice} />

      {branches.isPending ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : branches.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(branches.error, t)} retryLabel={t('retry')} retrying={branches.isFetching} onRetry={() => void branches.refetch()} />
      ) : branches.data.length === 0 ? (
        <EmptyState icon={Store} title={t('branches_empty')} text={t('branches_emptyText')} />
      ) : (
        <ul className="grid gap-4 lg:grid-cols-2">
          {branches.data.map((branch) => (
            <li key={branch.id}>
              <Card className="flex h-full flex-col gap-3">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <h2 className="text-headline font-bold text-fg">{branch.name}</h2>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      <Pill tone={branch.is_open_now ? 'success' : 'danger'}>{branch.is_open_now ? t('branches_openNow') : t('branches_closedNow')}</Pill>
                      <Pill tone={STATUS_TONE[branch.status] ?? 'neutral'}>{t(`branches_status_${branch.status as 'active'}`)}</Pill>
                      {!branch.is_active && <Pill tone="neutral">{t('branches_disabled')}</Pill>}
                    </div>
                  </div>
                  <div className="flex shrink-0 gap-1">
                    {canEdit && <IconButton icon={Pencil} label={t('branches_editAria', { name: branch.name })} onClick={() => setDialog({ kind: 'form', branch })} />}
                    {canCreate && <IconButton icon={Trash2} variant="danger" label={t('branches_deleteAria', { name: branch.name })} onClick={() => setDialog({ kind: 'delete', branch })} />}
                  </div>
                </div>
                <dl className="flex flex-col gap-1.5 text-footnote text-fg-muted">
                  <div className="flex items-center gap-2">
                    <Clock aria-hidden="true" className="size-4 shrink-0" />
                    <dt className="sr-only">{t('branches_hours')}</dt>
                    <dd className="text-fg">{hoursText(branch.working_hours, t)}</dd>
                    <dd className="text-caption">{branch.working_hours.timezone}</dd>
                  </div>
                  {branch.address && (
                    <div className="flex items-start gap-2">
                      <MapPin aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                      <dt className="sr-only">{t('branches_address')}</dt>
                      <dd className="text-fg">{branch.address}</dd>
                    </div>
                  )}
                  {branch.phone && (
                    <div className="flex items-center gap-2">
                      <Phone aria-hidden="true" className="size-4 shrink-0" />
                      <dt className="sr-only">{t('branches_phone')}</dt>
                      <dd className="text-fg">{branch.phone}</dd>
                    </div>
                  )}
                  <div className="text-caption">{t('branches_radiusLine', { km: branch.radius_km })}</div>
                </dl>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {dialog?.kind === 'form' && (
        <BranchFormDialog
          branch={dialog.branch}
          onClose={close}
          onSaved={(saved, created) => {
            setNotice(t(created ? 'branches_added' : 'branches_saved', { name: saved.name }));
            close();
          }}
        />
      )}
      {dialog?.kind === 'delete' && (
        <ConfirmDialog
          title={t('branches_deleteTitle', { name: dialog.branch.name })}
          text={t('branches_deleteText')}
          confirmLabel={t('mgmt_delete')}
          busy={remove.isPending || deactivate.isPending}
          error={deleteError?.message}
          onClose={close}
          onConfirm={() => {
            setDeleteError(null);
            remove.mutate(dialog.branch.id, {
              onSuccess: () => {
                setNotice(t('branches_deleted', { name: dialog.branch.name }));
                close();
              },
              onError: (err) => setDeleteError({ message: errorText(err, t), inUse: reasonOf(err) === 'branch_in_use' }),
            });
          }}
          extra={
            deleteError?.inUse && canEdit ? (
              <div className="flex flex-col gap-2 rounded-control bg-surface-2 p-3">
                <p className="text-footnote text-fg-muted">{t('branches_inUseHint')}</p>
                <Button
                  variant="secondary"
                  loading={deactivate.isPending}
                  onClick={() =>
                    deactivate.mutate(
                      { id: dialog.branch.id, input: { is_active: false, status: 'inactive' } },
                      {
                        onSuccess: () => {
                          setNotice(t('branches_deactivated', { name: dialog.branch.name }));
                          close();
                        },
                        onError: (err) => setDeleteError({ message: errorText(err, t), inUse: true }),
                      },
                    )
                  }
                >
                  {t('branches_deactivate')}
                </Button>
              </div>
            ) : null
          }
        />
      )}
    </>
  );
}
