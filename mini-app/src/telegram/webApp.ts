// The subset of Telegram's WebApp JS API (`https://telegram.org/js/telegram-web-app.js`)
// this app uses. Grows as later tasks touch more of it (Task 3 reads `themeParams`,
// `ready()`, `expand()` and now subscribes to `themeChanged`; the visual redesign adds
// `requestFullscreen`/`disableVerticalSwipes`/`enableClosingConfirmation` so the app
// opens full-screen, can't be swiped closed by accident, and confirms before closing;
// `contentSafeAreaInset` follows, so the app's own headers can clear the header bar
// Telegram draws over the content in full-screen mode).
export type TelegramEventType = 'themeChanged' | 'contentSafeAreaChanged'

export type TelegramSafeAreaInset = {
  top: number
  right: number
  bottom: number
  left: number
}

export type TelegramWebApp = {
  initData: string
  colorScheme: 'light' | 'dark'
  themeParams: Record<string, string>
  /** Bot API 8.0+. The area `env(safe-area-inset-*)` doesn't know about: Telegram's own
   * full-screen-mode chrome (the Close/⋯ header bar drawn over the top of the page).
   * `useTheme` reads this into a CSS var and combines it with `env()` via `max()`, since
   * either can be the larger inset depending on device and Telegram client version. */
  contentSafeAreaInset: TelegramSafeAreaInset
  ready(): void
  expand(): void
  close(): void
  /** Bot API 8.0+. Older clients no-op this silently rather than throwing — the
   * injected script itself handles the version gate, so this is called unconditionally
   * here, the same way `ready()`/`expand()` already are. */
  requestFullscreen(): void
  /** Bot API 7.7+. Stops the pull-down-to-close gesture from firing while the app is
   * open, so a swipe inside a scrollable panel can't accidentally close the mini app. */
  disableVerticalSwipes(): void
  /** Bot API 6.2+. Telegram asks the user to confirm before closing once this is on. */
  enableClosingConfirmation(): void
  onEvent(eventType: TelegramEventType, callback: () => void): void
  offEvent(eventType: TelegramEventType, callback: () => void): void
}

declare global {
  interface Window {
    Telegram?: { WebApp?: TelegramWebApp }
  }
}

// A null object, not `undefined`: outside Telegram (a browser tab during development,
// or a test) every caller can still call `webApp().ready()` without a guard.
function stub(): TelegramWebApp {
  return {
    initData: '',
    colorScheme: 'light',
    themeParams: {},
    contentSafeAreaInset: { top: 0, right: 0, bottom: 0, left: 0 },
    ready: () => {},
    expand: () => {},
    close: () => {},
    requestFullscreen: () => {},
    disableVerticalSwipes: () => {},
    enableClosingConfirmation: () => {},
    onEvent: () => {},
    offEvent: () => {},
  }
}

export function webApp(): TelegramWebApp {
  return window.Telegram?.WebApp ?? stub()
}
