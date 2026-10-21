import { CheckCircle2 } from 'lucide-react';

// "Saved" after an action: said once, in place, where the person is looking.
export function Notice({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <p role="status" className="mb-4 flex items-center gap-2 rounded-control bg-success-soft px-3 py-2.5 text-footnote font-bold text-success-soft-fg">
      <CheckCircle2 aria-hidden="true" className="size-4 shrink-0" />
      {message}
    </p>
  );
}

// A failure that belongs to the whole form, not to one field
export function FormError({ message }: { message: string | null | undefined }) {
  if (!message) return null;
  return (
    <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-footnote font-bold text-danger-soft-fg">
      {message}
    </p>
  );
}
