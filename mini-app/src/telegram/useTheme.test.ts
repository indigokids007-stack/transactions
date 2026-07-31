import { renderHook } from '@testing-library/react'
import { useTheme } from './useTheme'

function readVar(name: string): string {
  return document.documentElement.style.getPropertyValue(name)
}

afterEach(() => {
  delete window.Telegram
  document.documentElement.removeAttribute('style')
})

it('applies a readable light palette outside Telegram', () => {
  renderHook(() => useTheme())

  expect(readVar('--tg-bg')).toBe('#ffffff')
  expect(readVar('--tg-text')).toBe('#111111')
  expect(readVar('--tg-hint')).toBe('#707579')
  expect(readVar('--tg-button')).toBe('#2481cc')
  expect(readVar('--tg-button-text')).toBe('#ffffff')
})

it('applies the Telegram theme params when present, and calls ready/expand', () => {
  const ready = vi.fn()
  const expand = vi.fn()
  window.Telegram = {
    WebApp: {
      initData: '',
      colorScheme: 'dark',
      themeParams: { bg_color: '#000000', button_color: '#3390ec' },
      MainButton: {
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
      },
      ready,
      expand,
      close: () => {},
    },
  }

  renderHook(() => useTheme())

  expect(readVar('--tg-bg')).toBe('#000000')
  expect(readVar('--tg-button')).toBe('#3390ec')
  // A param Telegram did not send still falls back to the default.
  expect(readVar('--tg-hint')).toBe('#707579')
  expect(ready).toHaveBeenCalledOnce()
  expect(expand).toHaveBeenCalledOnce()
})
