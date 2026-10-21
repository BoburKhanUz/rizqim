import { TriangleAlert } from 'lucide-react';

// The reason a request failed, in words: the line of describeError and the messages no field of the form shows.
export function ErrorBanner({ message, details = [] }: { message: string; details?: string[] }) {
  return (
    <div role="alert" className="flex gap-3 rounded-card border border-line bg-danger-soft p-3 text-danger-soft-fg">
      <TriangleAlert aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
      <div className="min-w-0 text-body">
        <p className="font-bold">{message}</p>
        {details.length > 0 && (
          <ul className="mt-1 list-disc pl-5">
            {details.map((detail) => (
              <li key={detail}>{detail}</li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

export function SuccessNote({ children }: { children: string }) {
  return (
    <p role="status" className="rounded-card border border-line bg-success-soft p-3 text-body font-bold text-success-soft-fg">
      {children}
    </p>
  );
}
