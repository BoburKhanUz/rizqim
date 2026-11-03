import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import { createCombo, deleteCombo, fetchCombos, previewCombo, saveComboFiscalMode, updateCombo, type ComboFiscalMode, type ComboPick, type ComboSlotInput } from './api';

// Under ['owner', ...] like the products', so a sign-out clears them all
export const comboKeys = {
  list: ['owner', 'combos'] as const,
  preview: (id: number, picks: ComboPick[]) => ['owner', 'combos', id, 'preview', JSON.stringify(picks)] as const,
};

interface Done {
  onSuccess?: () => void;
}

export function useCombos() {
  const api = useStaffApi();
  return useQuery({ queryKey: comboKeys.list, queryFn: () => fetchCombos(api), retry: false });
}

export function useSaveCombo({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { id: number | null; productId?: number; slots?: ComboSlotInput[]; isActive?: boolean }) =>
      input.id === null ? createCombo(api, { productId: input.productId as number, slots: input.slots ?? [] }) : updateCombo(api, input.id, { slots: input.slots, isActive: input.isActive }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: comboKeys.list });
      onSuccess?.();
    },
  });
}

export function useDeleteCombo({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteCombo(api, id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: comboKeys.list });
      onSuccess?.();
    },
  });
}

export function useComboPreview(id: number | null, picks: ComboPick[]) {
  const api = useStaffApi();
  return useQuery({ queryKey: comboKeys.preview(id ?? 0, picks), queryFn: () => previewCombo(api, id as number, picks), enabled: id !== null, retry: false });
}

export function useSaveComboFiscalMode({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (mode: ComboFiscalMode) => saveComboFiscalMode(api, mode),
    onSuccess: () => {
      // The fiscal page reads the same settings
      void queryClient.invalidateQueries({ queryKey: ['owner'] });
      onSuccess?.();
    },
  });
}
