import type { TimelineTheme } from '@timeline-history/timeline-core';

/**
 * Ét kort (titel → billede → brødtekst) + lille tidslinje-markør.
 * Farver styres via --th-* på .th-theme-grid.
 */
export function ThemePreviewShot({ theme }: { theme: TimelineTheme }) {
  return (
    <span
      className={`th-theme-shot th-theme-shot--${theme}`}
      data-preview={theme}
    >
      <span
        className="th-theme-shot__capture"
        aria-hidden="true"
        data-theme-capture={theme}
      />
      <span className="th-theme-shot__mock" aria-hidden="true">
        <span className="th-theme-shot__bg" />
        <span className="th-theme-shot__rail" />
        <span className="th-theme-shot__dot" />
        <span className="th-theme-shot__card">
          <span className="th-theme-shot__date" />
          <span className="th-theme-shot__title" />
          <span className="th-theme-shot__title th-theme-shot__title--lead" />
          <span className="th-theme-shot__media" />
          <span className="th-theme-shot__text" />
          <span className="th-theme-shot__text th-theme-shot__text--b" />
        </span>
      </span>
    </span>
  );
}
