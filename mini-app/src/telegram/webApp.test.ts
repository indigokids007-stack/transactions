import { webApp } from './webApp'

afterEach(() => {
  delete window.Telegram
})

it('returns a null-object stub outside Telegram', () => {
  const app = webApp()

  expect(app.initData).toBe('')
  expect(app.colorScheme).toBe('light')
  expect(app.contentSafeAreaInset).toEqual({ top: 0, right: 0, bottom: 0, left: 0 })
  // None of these should throw with no Telegram runtime present.
  expect(() => app.ready()).not.toThrow()
  expect(() => app.expand()).not.toThrow()
  expect(() => app.requestFullscreen()).not.toThrow()
  expect(() => app.disableVerticalSwipes()).not.toThrow()
  expect(() => app.enableClosingConfirmation()).not.toThrow()
  expect(() => app.onEvent('themeChanged', () => {})).not.toThrow()
  expect(() => app.offEvent('themeChanged', () => {})).not.toThrow()
})

it('returns the real WebApp object when Telegram injects one', () => {
  const ready = vi.fn()
  const onEvent = vi.fn()
  window.Telegram = {
    WebApp: {
      initData: 'user=%7B%22id%22%3A1%7D',
      colorScheme: 'dark',
      themeParams: { bg_color: '#000000' },
      contentSafeAreaInset: { top: 44, right: 0, bottom: 0, left: 0 },
      ready,
      expand: () => {},
      close: () => {},
      requestFullscreen: () => {},
      disableVerticalSwipes: () => {},
      enableClosingConfirmation: () => {},
      onEvent,
      offEvent: () => {},
    },
  }

  const app = webApp()
  app.ready()
  const callback = () => {}
  app.onEvent('themeChanged', callback)

  expect(app.initData).toBe('user=%7B%22id%22%3A1%7D')
  expect(app.colorScheme).toBe('dark')
  expect(app.contentSafeAreaInset).toEqual({ top: 44, right: 0, bottom: 0, left: 0 })
  expect(ready).toHaveBeenCalledOnce()
  expect(onEvent).toHaveBeenCalledWith('themeChanged', callback)
})
