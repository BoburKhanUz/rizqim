import { Search, X } from 'lucide-react';
import { useLanguageStore } from '../../store/languageStore';
import { INPUT_CLASS } from './fieldStyles';

interface SearchFieldProps {
  value: string;
  onChange: (value: string) => void;
  placeholder: string;
}

// The search box of the lists: 44px, 17px text, a clear button with a 44px target.
export function SearchField({ value, onChange, placeholder }: SearchFieldProps) {
  const { language } = useLanguageStore();
  return (
    <div className="relative">
      <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
      <input
        type="text"
        enterKeyHint="search"
        aria-label={placeholder}
        placeholder={placeholder}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={`${INPUT_CLASS} pl-10 pr-11`}
      />
      {value && (
        <button
          type="button"
          aria-label={language === 'uz' ? 'Qidiruvni tozalash' : 'Очистить поиск'}
          onClick={() => onChange('')}
          className="absolute right-0 top-0 flex size-11 cursor-pointer items-center justify-center text-fg-muted"
        >
          <X aria-hidden="true" className="size-5" />
        </button>
      )}
    </div>
  );
}
