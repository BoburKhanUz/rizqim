import { useMainButtonFallback } from '../../hooks/useMainButton';
import { Button } from './Button';

// The in-page stand-in for Telegram's MainButton (hooks/useMainButton.ts), drawn in the bottom dock above the
// navigation. Renders nothing while no screen asks for a main button or while Telegram's own is used.
export function MainButtonHost() {
  const { owner, text, enabled, progress, visible, onClick } = useMainButtonFallback();
  if (!owner || !visible) return null;
  return (
    <div className="px-4 pb-3">
      <Button size="lg" fullWidth disabled={!enabled} loading={progress} onClick={onClick} className="shadow-lg">
        {text}
      </Button>
    </div>
  );
}
