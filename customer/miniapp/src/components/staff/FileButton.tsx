import type { ChangeEvent } from 'react';
import type { LucideIcon } from 'lucide-react';
import { buttonClasses } from '../ui/buttonStyles';

interface FileButtonProps {
  label: string;
  icon?: LucideIcon;
  accept: string;
  onFile: (event: ChangeEvent<HTMLInputElement>) => void;
  disabled?: boolean;
}

// "Choose a file" drawn as a secondary button. The real <input type=file> is visually hidden but stays focusable, and the
// label shows the focus ring when it has focus.
export function FileButton({ label, icon: Icon, accept, onFile, disabled }: FileButtonProps) {
  return (
    <label className={`${buttonClasses({ variant: 'secondary' })} focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand`} aria-disabled={disabled || undefined}>
      {Icon && <Icon aria-hidden="true" className="size-[18px]" />}
      {label}
      <input type="file" accept={accept} onChange={onFile} disabled={disabled} className="sr-only" />
    </label>
  );
}
