import { useId } from 'react';
import { cx } from '@rizqim/ui';

// The RIZQIM logo for the Mini App header: the brand mark (infra/landing/site/img/logo-mark.svg, inlined so nothing
// is fetched) and the wordmark. The mark keeps its brand colours (#F97316 -> #C2410C, accent #FBBF24 -> #F59E0B),
// which read on Telegram's light and dark backgrounds alike; the wordmark is text in the theme's foreground colour,
// so it follows the theme. 28px tall: about the height of Telegram's own full-screen buttons.
//
// Accessible as one image named "Rizqim" (inside the page's <h1>, the heading is named "Rizqim" too).
export function BrandLogo({ className }: { className?: string }) {
  // Unique gradient ids: two logos on a page must not share them
  const id = useId().replace(/[^a-zA-Z0-9_-]/g, '');
  const body = `rz-logo-body-${id}`;
  const leaf = `rz-logo-leaf-${id}`;
  return (
    <span role="img" aria-label="Rizqim" className={cx('inline-flex items-center gap-2', className)}>
      <svg aria-hidden="true" focusable="false" viewBox="180 240 820 720" className="h-7 w-8 shrink-0">
        <defs>
          <linearGradient id={body} x1="0.15" y1="0" x2="0.85" y2="1">
            <stop offset="0" stopColor="#F97316" />
            <stop offset="1" stopColor="#C2410C" />
          </linearGradient>
          <linearGradient id={leaf} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor="#FBBF24" />
            <stop offset="1" stopColor="#F59E0B" />
          </linearGradient>
        </defs>
        <path
          fill={`url(#${body})`}
          d="M235 800L405 345C440 290 490 255 560 255H800C920 255 995 340 995 450C995 560 930 685 800 685L790 686C700 686 600 690 540 735C470 785 425 860 390 930C380 945 370 948 355 948H190C225 850 340 740 480 668C540 638 590 612 606 606C500 625 400 660 330 712C290 742 260 772 235 800Z"
        />
        <path fill={`url(#${leaf})`} d="M543 766C600 730 680 708 780 710C805 711 815 722 822 730L978 948H712C690 948 672 940 660 925Z" />
        <path fill="#fff" d="M497 555C505 490 560 430 650 428C740 430 795 490 802 555Z" />
        <circle cx="659" cy="404" r="24" fill="#fff" />
        <rect x="465" y="577" width="367" height="28" rx="14" fill="#fff" />
        <path fill="#E8590C" d="M520 540C530 500 560 468 612 452C575 475 555 505 548 540Z" />
        <g fill="none" stroke="#fff" strokeWidth="9" strokeLinecap="round">
          <path d="M714 304C698 326 724 346 712 372C709 384 706 392 702 398" />
          <path d="M754 304C738 326 764 346 752 372C749 384 746 392 742 398" />
        </g>
      </svg>
      <span aria-hidden="true" className="text-title font-extrabold tracking-tight text-fg">
        Rizqim
      </span>
    </span>
  );
}
