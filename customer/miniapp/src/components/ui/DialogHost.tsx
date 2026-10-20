import { useDialogQueue } from '../../lib/dialogs';
import { Button } from '@rizqim/ui';
import { Dialog } from './Dialog';

// Draws the in-app popups of showAlert / showConfirm / showPopup (lib/dialogs.ts) when Telegram's are not
// available: one at a time, in the order they were asked. Mounted once, in App.
export function DialogHost() {
  const current = useDialogQueue((state) => state.queue[0]);
  if (!current) return null;
  const cancel = current.buttons.find((button) => button.type === 'cancel');
  return (
    <Dialog
      key={current.id}
      open
      variant="center"
      role="alertdialog"
      title={current.title ?? current.message}
      description={current.title ? current.message : undefined}
      hideClose
      onClose={() => current.resolve(cancel ? cancel.id : null)}
      footer={current.buttons.map((button, index) => (
        <Button
          key={button.id}
          variant={button.type === 'cancel' ? 'secondary' : button.type === 'destructive' ? 'danger' : 'primary'}
          data-autofocus={index === current.buttons.length - 1 ? true : undefined}
          onClick={() => current.resolve(button.id)}
        >
          {button.text}
        </Button>
      ))}
    />
  );
}
