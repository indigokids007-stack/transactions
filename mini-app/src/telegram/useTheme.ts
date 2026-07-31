import { useEffect } from 'react'
import { webApp } from './webApp'

// One row per CSS custom property this app consumes. `fallback` is the readable light
// palette used outside Telegram (a browser tab during development, or a test), so the
// shell never depends on `themeParams` being present.
const THEME_MAPPINGS: { cssVar: string; paramKey: string; fallback: string }[] = [
  { cssVar: '--tg-bg', paramKey: 'bg_color', fallback: '#ffffff' },
  { cssVar: '--tg-text', paramKey: 'text_color', fallback: '#111111' },
  { cssVar: '--tg-hint', paramKey: 'hint_color', fallback: '#707579' },
  { cssVar: '--tg-button', paramKey: 'button_color', fallback: '#2481cc' },
  { cssVar: '--tg-button-text', paramKey: 'button_text_color', fallback: '#ffffff' },
  // Unselected keypad/chip/cancel backgrounds (`PeriodPicker`, `CategoryChips`,
  // `DetailsSheet`'s type switch, `AmountKeypad`'s cancel) style themselves against this
  // variable. Left undefined, the browser fell back to `initial` — a mismatch that was
  // most visible in dark theme, where "unselected" and "invisible" started to look alike.
  { cssVar: '--tg-secondary-bg', paramKey: 'secondary_bg_color', fallback: '#f0f0f0' },
]

// Synchronises the document with Telegram's WebApp runtime: applies its theme colours as
// CSS custom properties, tells the client the app is ready to be shown, and keeps
// listening for `themeChanged` (the user can switch Telegram's own theme while the app
// is open). Runs once per mount — nothing reactive from render feeds it, so there is
// nothing to add to the dependency array — but it does subscribe to an external event,
// so it unsubscribes the same listener on cleanup.
export function useTheme(): void {
  useEffect(() => {
    const app = webApp()
    const root = document.documentElement

    function applyTheme(): void {
      for (const { cssVar, paramKey, fallback } of THEME_MAPPINGS) {
        root.style.setProperty(cssVar, app.themeParams[paramKey] ?? fallback)
      }
    }

    applyTheme()
    app.ready()
    app.expand()

    app.onEvent('themeChanged', applyTheme)
    return () => {
      app.offEvent('themeChanged', applyTheme)
    }
  }, [])
}
