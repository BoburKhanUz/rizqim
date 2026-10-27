import type { Metadata, Viewport } from 'next';
import { DEFAULT_LANGUAGE } from '@/i18n';
import { Providers } from '@/components/Providers';
import './globals.css';

export const metadata: Metadata = {
  title: { default: 'RIZQIM boshqaruv markazi', template: '%s · RIZQIM' },
  description: 'RIZQIM platformasi jamoasi uchun boshqaruv markazi',
  robots: { index: false, follow: false },
};

export const viewport: Viewport = {
  colorScheme: 'light dark',
  themeColor: [
    { media: '(prefers-color-scheme: light)', color: '#f4f5f7' },
    { media: '(prefers-color-scheme: dark)', color: '#0e1621' },
  ],
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang={DEFAULT_LANGUAGE}>
      <body className="min-h-screen bg-page text-fg">
        <Providers language={DEFAULT_LANGUAGE}>{children}</Providers>
      </body>
    </html>
  );
}
