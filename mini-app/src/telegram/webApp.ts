// The subset of Telegram's WebApp JS API (`https://telegram.org/js/telegram-web-app.js`)
// this app uses. Grows as later tasks touch more of it (Task 3 reads `themeParams`,
// `ready()` and `expand()`; a later task drives `MainButton` from the entry form).
export type TelegramMainButton = {
  text: string
  isVisible: boolean
  isActive: boolean
  setText(text: string): void
  show(): void
  hide(): void
  enable(): void
  disable(): void
  onClick(callback: () => void): void
  offClick(callback: () => void): void
}

export type TelegramWebApp = {
  initData: string
  colorScheme: 'light' | 'dark'
  themeParams: Record<string, string>
  MainButton: TelegramMainButton
  ready(): void
  expand(): void
  close(): void
}

declare global {
  interface Window {
    Telegram?: { WebApp?: TelegramWebApp }
  }
}

function noopMainButton(): TelegramMainButton {
  return {
    text: '',
    isVisible: false,
    isActive: true,
    setText: () => {},
    show: () => {},
    hide: () => {},
    enable: () => {},
    disable: () => {},
    onClick: () => {},
    offClick: () => {},
  }
}

// A null object, not `undefined`: outside Telegram (a browser tab during development,
// or a test) every caller can still call `webApp().ready()` without a guard.
function stub(): TelegramWebApp {
  return {
    initData: '',
    colorScheme: 'light',
    themeParams: {},
    MainButton: noopMainButton(),
    ready: () => {},
    expand: () => {},
    close: () => {},
  }
}

export function webApp(): TelegramWebApp {
  return window.Telegram?.WebApp ?? stub()
}
