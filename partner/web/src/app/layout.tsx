import type { Metadata, Viewport } from 'next';
import { cookies } from 'next/headers';
import { DEFAULT_LANGUAGE, isLanguage, LANGUAGE_COOKIE } from '@/i18n';
import { publicApiBaseUrl } from '@/lib/config';
import { Providers } from '@/components/Providers';
import './globals.css';

// Read per request: the API address comes from the environment of the running server, not from the build
export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: { default: 'Rizqim Hamkor', template: '%s · Rizqim Hamkor' },
  description: 'Restoran egasi va menejeri uchun boshqaruv paneli',
  robots: { index: false, follow: false },
};

export const viewport: Viewport = {
  colorScheme: 'light dark',
  themeColor: [
    { media: '(prefers-color-scheme: light)', color: '#f4f5f7' },
    { media: '(prefers-color-scheme: dark)', color: '#0e1621' },
  ],
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const saved = (await cookies()).get(LANGUAGE_COOKIE)?.value;
  const language = isLanguage(saved) ? saved : DEFAULT_LANGUAGE;
  return (
    <html lang={language}>
      <body className="min-h-screen bg-page text-fg">
        <Providers language={language} apiBaseUrl={publicApiBaseUrl()}>
          {children}
        </Providers>
      </body>
    </html>
  );
}
