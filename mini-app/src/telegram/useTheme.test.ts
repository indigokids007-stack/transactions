import { renderHook } from '@testing-library/react'
import { useTheme } from './useTheme'

function readVar(name: string): string {
  return document.documentElement.style.getPropertyValue(name)
}

afterEach(() => {
  delete window.Telegram
  document.documentElement.removeAttribute('style')
  document.documentElement.removeAttribute('data-theme')
})

it('applies a readable light palette outside Telegram', () => {
  renderHook(() => useTheme())

  expect(readVar('--tg-bg')).toBe('#ffffff')
  expect(readVar('--tg-text')).toBe('#111111')
  expect(readVar('--tg-hint')).toBe('#707579')
  expect(readVar('--tg-button')).toBe('#2481cc')
  expect(readVar('--tg-button-text')).toBe('#ffffff')
  expect(readVar('--tg-secondary-bg')).toBe('#f0f0f0')
})

it('applies the Telegram theme params when present, and drives the app-shell lifecycle calls', () => {
  const ready = vi.fn()
  const expand = vi.fn()
  const requestFullscreen = vi.fn()
  const disableVerticalSwipes = vi.fn()
  const enableClosingConfirmation = vi.fn()
  window.Telegram = {
    WebApp: {
      initData: '',
      colorScheme: 'dark',
      themeParams: { bg_color: '#000000', button_color: '#3390ec', secondary_bg_color: '#181818' },
      contentSafeAreaInset: { top: 0, right: 0, bottom: 0, left: 0 },
      ready,
      expand,
      close: () => {},
      requestFullscreen,
      disableVerticalSwipes,
      enableClosingConfirmation,
      onEvent: () => {},
      offEvent: () => {},
    },
  }

  renderHook(() => useTheme())

  expect(readVar('--tg-bg')).toBe('#000000')
  expect(readVar('--tg-button')).toBe('#3390ec')
  expect(readVar('--tg-secondary-bg')).toBe('#181818')
  // A param Telegram did not send still falls back to the default.
  expect(readVar('--tg-hint')).toBe('#707579')
  expect(ready).toHaveBeenCalledOnce()
  expect(expand).toHaveBeenCalledOnce()
  // Opens full-screen, blocks the pull-down-to-close swipe, and asks Telegram to confirm
  // before closing — the mini-app-shell behaviour this task adds alongside ready/expand.
  expect(requestFullscreen).toHaveBeenCalledOnce()
  expect(disableVerticalSwipes).toHaveBeenCalledOnce()
  expect(enableClosingConfirmation).toHaveBeenCalledOnce()
})

it("sets data-theme from Telegram's colorScheme, independent of the OS setting", () => {
  window.Telegram = {
    WebApp: {
      initData: '',
      colorScheme: 'dark',
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
    },
  }

  renderHook(() => useTheme())

  expect(document.documentElement.dataset.theme).toBe('dark')
})

it("sets data-theme to 'light' when Telegram's colorScheme is light", () => {
  window.Telegram = {
    WebApp: {
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
    },
  }

  renderHook(() => useTheme())

  expect(document.documentElement.dataset.theme).toBe('light')
})

it('re-applies the theme when Telegram fires themeChanged, and unsubscribes on cleanup', () => {
  let themeChangedCallback: (() => void) | undefined
  const offEvent = vi.fn()
  const themeParams: Record<string, string> = { bg_color: '#111111' }

  window.Telegram = {
    WebApp: {
      initData: '',
      colorScheme: 'dark',
      themeParams,
      contentSafeAreaInset: { top: 0, right: 0, bottom: 0, left: 0 },
      ready: () => {},
      expand: () => {},
      close: () => {},
      requestFullscreen: () => {},
      disableVerticalSwipes: () => {},
      enableClosingConfirmation: () => {},
      onEvent: (eventType, callback) => {
        if (eventType === 'themeChanged') themeChangedCallback = callback
      },
      offEvent,
    },
  }

  const { unmount } = renderHook(() => useTheme())

  expect(readVar('--tg-bg')).toBe('#111111')

  // Telegram updates its own theme params, then notifies the app.
  themeParams.bg_color = '#222222'
  themeChangedCallback?.()

  expect(readVar('--tg-bg')).toBe('#222222')

  unmount()
  expect(offEvent).toHaveBeenCalledWith('themeChanged', themeChangedCallback)
})

// The header bar Telegram draws over the page in full-screen mode — a separate
// obstruction from the device's own notch/status bar (`env(safe-area-inset-top)`), and
// one that can change (e.g. entering/exiting full-screen), so this mirrors the
// `themeChanged` test above: applied on mount, re-applied on the matching event, and
// unsubscribed on cleanup.
it('applies contentSafeAreaInset.top as a CSS var, re-applies on contentSafeAreaChanged, and unsubscribes on cleanup', () => {
  let safeAreaChangedCallback: (() => void) | undefined
  const offEvent = vi.fn()
  const contentSafeAreaInset = { top: 44, right: 0, bottom: 0, left: 0 }

  window.Telegram = {
    WebApp: {
      initData: '',
      colorScheme: 'light',
      themeParams: {},
      contentSafeAreaInset,
      ready: () => {},
      expand: () => {},
      close: () => {},
      requestFullscreen: () => {},
      disableVerticalSwipes: () => {},
      enableClosingConfirmation: () => {},
      onEvent: (eventType, callback) => {
        if (eventType === 'contentSafeAreaChanged') safeAreaChangedCallback = callback
      },
      offEvent,
    },
  }

  const { unmount } = renderHook(() => useTheme())

  expect(readVar('--tg-content-safe-top')).toBe('44px')

  // Telegram enters/exits full-screen, its own header bar changes height, then it
  // notifies the app.
  contentSafeAreaInset.top = 0
  safeAreaChangedCallback?.()

  expect(readVar('--tg-content-safe-top')).toBe('0px')

  unmount()
  expect(offEvent).toHaveBeenCalledWith('contentSafeAreaChanged', safeAreaChangedCallback)
})
