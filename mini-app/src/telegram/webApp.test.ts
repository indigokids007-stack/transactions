import { webApp } from './webApp'

afterEach(() => {
  delete window.Telegram
})

it('returns a null-object stub outside Telegram', () => {
  const app = webApp()

  expect(app.initData).toBe('')
  expect(app.colorScheme).toBe('light')
  // None of these should throw with no Telegram runtime present.
  expect(() => app.ready()).not.toThrow()
  expect(() => app.expand()).not.toThrow()
  expect(() => app.MainButton.show()).not.toThrow()
})

it('returns the real WebApp object when Telegram injects one', () => {
  const ready = vi.fn()
  window.Telegram = {
    WebApp: {
      initData: 'user=%7B%22id%22%3A1%7D',
      colorScheme: 'dark',
      themeParams: { bg_color: '#000000' },
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
      expand: () => {},
      close: () => {},
    },
  }

  const app = webApp()
  app.ready()

  expect(app.initData).toBe('user=%7B%22id%22%3A1%7D')
  expect(app.colorScheme).toBe('dark')
  expect(ready).toHaveBeenCalledOnce()
})
