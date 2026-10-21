import { useState } from 'react';
import { type Localized } from '@rizqim/staff';
import { useStaffText } from '../hooks/useStaffText';
import { useWorkBranch } from '../hooks/useWorkBranch';
import { InlineError, PageHeader } from '../components/ui';
import { BranchSelect } from '../components/staff/BranchSelect';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import { StopListPanel } from '../components/staff/StopListPanel';
import { BatchesPanel } from '../components/staff/BatchesPanel';

const TEXT = {
  title: { uz: 'Stop-list va qozon', ru: 'Стоп-лист и котлы' },
  subtitle: { uz: "Tugagan taomlar va qozon qoldig'i", ru: 'Закончившиеся блюда и остаток котлов' },
  tabs: { uz: "Bo'lim", ru: 'Раздел' },
  stopList: { uz: 'Stop-list', ru: 'Стоп-лист' },
  batches: { uz: 'Qozon', ru: 'Котёл' },
  branch: { uz: 'Filial', ru: 'Филиал' },
} satisfies Record<string, Localized>;

// What a manager or an owner does at the counter: mark a dish "tugadi" (and back), and watch or refill the pot. Both are
// per branch; an owner picks the branch, a manager works with their own.
export default function StopListBatches() {
  const tx = useStaffText(TEXT);
  const [tab, setTab] = useState<'stoplist' | 'batches'>('stoplist');
  const branch = useWorkBranch();

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title={tx('title')} subtitle={tx('subtitle')} back />

      {branch.canPick && <BranchSelect branches={branch.branches} value={branch.branchId} onChange={branch.setBranchId} label={tx('branch')} />}

      <SegmentedControl
        label={tx('tabs')}
        value={tab}
        onChange={setTab}
        segments={[
          { value: 'stoplist', label: tx('stopList') },
          { value: 'batches', label: tx('batches') },
        ]}
      />

      {branch.isError ? (
        <InlineError onRetry={branch.refetch} />
      ) : tab === 'stoplist' ? (
        <StopListPanel branchId={branch.branchId} ready={branch.ready} />
      ) : (
        <BatchesPanel branchId={branch.branchId} ready={branch.ready} />
      )}
    </div>
  );
}
