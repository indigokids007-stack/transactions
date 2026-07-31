// Vite's `?raw` suffix (declared by `vite/client`, already in this project's `types`) reads
// the file as plain text, so this stays in the same Vite/browser world as the rest of the
// app instead of reaching for Node's `fs` (which this tsconfig's `types` list doesn't
// expose).
import html from '../../index.html?raw'

// Telegram's own documentation (https://core.telegram.org/bots/webapps) requires
// `telegram-web-app.js` to load, inside `<head>`, before any other script on the page.
// Miss that and `window.Telegram.WebApp` is never created: `webApp()` (see `./webApp.ts`)
// silently falls back to its no-Telegram stub, and the app can never authenticate. No
// runtime test can catch a static HTML ordering mistake like that, so this reads
// `index.html` itself and asserts the ordering directly.
it('loads the Telegram WebApp SDK before any other script', () => {
  const telegramScriptIndex = html.indexOf('telegram-web-app.js')
  const moduleScriptIndex = html.indexOf('/src/main.tsx')

  expect(telegramScriptIndex).toBeGreaterThan(-1)
  expect(moduleScriptIndex).toBeGreaterThan(-1)
  expect(telegramScriptIndex).toBeLessThan(moduleScriptIndex)
})

it('places the Telegram WebApp SDK inside <head>', () => {
  const telegramScriptIndex = html.indexOf('telegram-web-app.js')
  const headCloseIndex = html.indexOf('</head>')

  expect(telegramScriptIndex).toBeGreaterThan(-1)
  expect(headCloseIndex).toBeGreaterThan(-1)
  expect(telegramScriptIndex).toBeLessThan(headCloseIndex)
})
