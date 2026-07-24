/**
 * Tailwind config for the admin portal and (from Phase 5) the public site.
 *
 * Colours resolve to CSS custom properties rather than literals, so the brand
 * direction chosen in Phase 5 is applied by editing the variables in app.css —
 * no class renaming across templates.
 *
 * Built with the standalone Tailwind CLI, so the project needs no Node runtime
 * and no node_modules, locally or in production.
 */
module.exports = {
  content: [
    './app/Views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        ink:     'rgb(var(--c-ink) / <alpha-value>)',
        surface: 'rgb(var(--c-surface) / <alpha-value>)',
        raised:  'rgb(var(--c-raised) / <alpha-value>)',
        line:    'rgb(var(--c-line) / <alpha-value>)',
        // Boundaries of interactive controls. Separate from --line because WCAG
        // 1.4.11 requires 3:1 for those, while a decorative hairline is exempt —
        // one token cannot satisfy both without making every divider shout.
        field:   'rgb(var(--c-field) / <alpha-value>)',
        body:    'rgb(var(--c-body) / <alpha-value>)',
        muted:   'rgb(var(--c-muted) / <alpha-value>)',
        accent:  'rgb(var(--c-accent) / <alpha-value>)',
        positive:'rgb(var(--c-positive) / <alpha-value>)',
        warning: 'rgb(var(--c-warning) / <alpha-value>)',
        danger:  'rgb(var(--c-danger) / <alpha-value>)',
      },
      fontFamily: {
        // System stack in the admin: zero font files, zero layout shift, and it
        // keeps font-src 'self' honest without shipping webfonts to a back office.
        sans: ['ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      borderRadius: {
        card: '14px',
      },
      boxShadow: {
        card: '0 1px 2px rgb(0 0 0 / 0.30), 0 8px 24px -12px rgb(0 0 0 / 0.45)',
        segment: 'inset 0 1px 0 rgb(255 255 255 / 0.04)',
      },
      keyframes: {
        'fade-up': {
          '0%':   { opacity: '0', transform: 'translateY(6px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        'fade-up': 'fade-up .28s ease-out both',
      },
    },
  },
  plugins: [],
};
