import { useId, type ReactNode } from 'react';
import { cx } from './cx';

interface FieldProps {
  label: ReactNode;
  // Shown below the control in the muted colour
  hint?: ReactNode;
  // Shown below the control as an error (role="alert")
  error?: ReactNode;
  className?: string;
  // The control; spread `controlProps` on it so the label, the hint and the error are tied to it
  children: (controlProps: { id: string; 'aria-describedby'?: string; 'aria-invalid'?: true }) => ReactNode;
}

// A labelled form control: the label is a real <label for>, the hint and the error are read with the control.
export function Field({ label, hint, error, className, children }: FieldProps) {
  const id = useId();
  const noteId = `${id}-note`;
  const note = error ?? hint;
  return (
    <div className={cx('flex flex-col gap-1.5', className)}>
      <label htmlFor={id} className="text-footnote font-bold text-fg-muted">
        {label}
      </label>
      {children({ id, 'aria-describedby': note ? noteId : undefined, 'aria-invalid': error ? true : undefined })}
      {note && (
        <p id={noteId} role={error ? 'alert' : undefined} className={cx('text-footnote', error ? 'font-bold text-danger-soft-fg' : 'text-fg-muted')}>
          {note}
        </p>
      )}
    </div>
  );
}
