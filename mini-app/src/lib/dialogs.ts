import { create } from 'zustand';
import { useLanguageStore, type TranslationKey } from '../store/languageStore';
import { nativePopup, type PopupButtonSpec } from './twa';

// showAlert / showConfirm / showPopup: Telegram's native popups inside Telegram, an in-app dialog anywhere else
// (components/ui/DialogHost.tsx draws it). Never window.alert/confirm: they block the page, look foreign and are
// not translated. Each returns a Promise of the answer.
//
//   await showAlert("Baho yuborildi! Rahmat.");
//   if (await showConfirm("Mahsulot o'chirilsinmi?", { confirmText: "O'chirish", destructive: true })) ...

export interface PopupRequest {
  id: number;
  title?: string;
  message: string;
  buttons: PopupButtonSpec[];
  resolve: (buttonId: string | null) => void;
}

interface DialogQueue {
  queue: PopupRequest[];
}

export const useDialogQueue = create<DialogQueue>(() => ({ queue: [] }));

let nextId = 1;

function showInApp(title: string | undefined, message: string, buttons: PopupButtonSpec[]): Promise<string | null> {
  return new Promise((resolve) => {
    const request: PopupRequest = {
      id: nextId++,
      title,
      message,
      buttons,
      resolve: (buttonId) => {
        useDialogQueue.setState((state) => ({ queue: state.queue.filter((item) => item.id !== request.id) }));
        resolve(buttonId);
      },
    };
    useDialogQueue.setState((state) => ({ queue: [...state.queue, request] }));
  });
}

// A popup with up to three buttons; resolves with the id of the pressed one, null when dismissed.
export function showPopup({ title, message, buttons }: { title?: string; message: string; buttons: PopupButtonSpec[] }): Promise<string | null> {
  return nativePopup(title, message, buttons) || showInApp(title, message, buttons);
}

const t = (key: TranslationKey) => useLanguageStore.getState().t(key);

// A message with one OK button; resolves when it is closed.
export async function showAlert(message: string, options: { title?: string } = {}): Promise<void> {
  await showPopup({ title: options.title, message, buttons: [{ id: 'ok', text: t('ok') }] });
}

// A yes/no question; resolves true only when confirmed. `destructive` paints the confirm button red.
export async function showConfirm(
  message: string,
  options: { title?: string; confirmText?: string; cancelText?: string; destructive?: boolean } = {},
): Promise<boolean> {
  const answer = await showPopup({
    title: options.title,
    message,
    buttons: [
      { id: 'cancel', type: 'cancel', text: options.cancelText ?? t('dialogCancel') },
      { id: 'ok', type: options.destructive ? 'destructive' : 'default', text: options.confirmText ?? t('dialogConfirm') },
    ],
  });
  return answer === 'ok';
}
