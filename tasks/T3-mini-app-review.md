# Review: T3 mini app

## Round 1, gpt-5.6-sol

• Your ask: not shipped — nine-task implementation mavjud, lekin mini app hozir Telegram ichida ishlamaydi va
  ayrim moliyaviy qiymatlarni noto‘g‘ri ko‘rsatishi mumkin.

  - BLOCKER — Telegram SDK production HTML’ga ulanmagan. mini-app/index.html:3 faqat Vite modulini yuklaydi.
    Shuning uchun window.Telegram.WebApp yaratilmaydi, mini-app/src/telegram/webApp.ts:55 bo‘sh initData bilan
    stub qaytaradi va mini-app/src/AppRoot.tsx:21 autentifikatsiyaga bo‘sh qiymat yuboradi. Telegram rasmiy
    qo‘llanmasi telegram-web-app.js skriptini boshqa skriptlardan oldin <head> ichiga qo‘yishni talab qiladi.
    Telegram Mini Apps documentation (https://core.telegram.org/bots/webapps)

  - BLOCKER — report aggregatlari JavaScript safe-integer chegarasidan chiqib, jim yaxlitlanadi. Har bir
    transaction 1_000_000_000_000_000 minor unitgacha ruxsat etilgan, lekin report bu qiymatlarni cheklovsiz
    jamlaydi. mini-app/src/api/types.ts:72 aggregatni number deb o‘qiydi va mini-app/src/ui/Money.tsx:3 shu
    noto‘g‘ri raqamni formatlaydi. Masalan, bir valyutadagi o‘nta maksimum transaction va yana 1 minor unit
    API’da 10000000000000001 bo‘ladi, lekin JSON.parse uni 10000000000000000 qiladi. Bu valyutalarni
    aralashtirish emas, bir valyuta ichidagi moliyaviy aniqlik buzilishi.

  - MAJOR — foydalanuvchi tasdiqlangan amount grammar’ning katta qismini kirita olmaydi. mini-app/src/entry/
    AmountKeypad.tsx:11 faqat raqam, nuqta va backspace beradi; mini-app/src/entry/EntryScreen.tsx:78 amount
    uchun matn inputi yoki keyboard handler bermaydi. Natijada comma, k, ming, mln, mlrd va Cyrillic
    variantlari parserda testlangan bo‘lsa ham UI orqali kiritib bo‘lmaydi. Noto‘g‘ri shakl ham faqat Save’ni
    o‘chiradi, talab qilingan izohni ko‘rsatmaydi.

  - MAJOR — history’dagi currency edit har doim 422 bilan tugaydi. mini-app/src/history/
    TransactionFields.tsx:55 valyutani tahrirlashga ruxsat beradi, lekin mini-app/src/history/
    transactionEdit.ts:49 PATCH’da faqat currency yuboradi. Backend contract valyuta o‘zgarsa amount ham
    kelishini talab qiladi va bu holatni alohida test bilan rad etadi: tests/Feature/Api/
    UpdateDeleteTransactionTest.php:105. Amount editing out-of-scope bo‘lgani uchun hozirgi currency control
    ishlamaydigan control.

  - MAJOR — “today” va “this month” UTC bo‘yicha hisoblanadi. mini-app/src/entry/entryDefaults.ts:16
    toISOString() ishlatadi; mini-app/src/reports/usePeriod.ts:26 esa UTC oyini oladi. Masalan, Toshkentda
    2026-08-01 00:30 paytida entry sanasi 2026-07-31, report va history esa iyul oyini tanlaydi.

  - MAJOR — filter almashganda eski pagination natijasi yangi history ichiga qo‘shilishi mumkin. Birinchi-page
    effect ignore cleanup bilan himoyalangan, lekin mini-app/src/history/useTransactions.ts:100 dagi loadMore()
    himoyalanmagan. Category A uchun keyingi page yuklanayotganda Category B tanlansa, B’ning birinchi page’i
    kelgach eski A page’i setItems(current => [...current, ...page.data]) orqali unga qo‘shiladi. loading guard
    yo‘qligi sababli observer bir cursorni parallel ravishda qayta so‘rashi ham mumkin.

  - MAJOR — approved custom date range UI’da yo‘q. Dizayn bitta picker’da “this month, last month, custom
    range” talab qiladi, lekin mini-app/src/reports/PeriodPicker.tsx:8 faqat ikki preset ko‘rsatadi. setRange()
    mavjud, ammo Reports yoki History’dan unga yetib bo‘lmaydi.

  - MAJOR — history user prop’ini e’tiborsiz qoldirib, barcha ko‘rinadigan yozuvlarda edit/delete ko‘rsatadi.
    mini-app/src/history/HistoryScreen.tsx:13 buni ochiq aytadi. Manager boshqa department xodimlarining
    yozuvlarini ko‘radi, owner esa hammasini ko‘radi, ammo backend managerga faqat o‘z yozuvlarini
    o‘zgartirishga ruxsat beradi va ownerga umuman bermaydi: app/Policies/TransactionPolicy.php:17. Shuning
    uchun UI foydalanuvchiga oldindan ma’lum bo‘lgan 403 actionlarni taklif qiladi.

  - MAJOR — history sheet tasdiqlangan “full record and revision count”ni bermaydi. mini-app/src/history/
    TransactionSheet.tsx:69 faqat edit fieldlari va actionlarni ko‘rsatadi; amount, user, department va
    revision count ko‘rinmaydi. mini-app/src/api/client.ts:19 backenddagi revisions endpointini ham expose
    qilmagan. Amountni tahrirlamaslik settled tradeoff, ammo uni read-only ko‘rsatish bundan tashqarida emas.

  - MAJOR — report xatolari retry va 429 contractini bajarmaydi. Summary, Trend va Staff view’lar ApiErrorni
    tashlab yuborib, action’siz generic ErrorState ko‘rsatadi; masalan mini-app/src/reports/SummaryView.tsx:92.
    Vaqtinchalik network xatosidan keyin shu view ichida retry tugmasi yo‘q, 429 esa talab qilingan “too many
    requests” xabariga ajratilmaydi.

  - MINOR — --tg-secondary-bg hech qayerda aniqlanmagan. Ko‘p keypad/chip/cancel elementlari uni ishlatadi,
    ammo mini-app/src/telegram/useTheme.ts:7 va mini-app/src/styles.css:9 bu variable’ni bermaydi. Unselected
    control backgroundlari, ayniqsa dark theme’da, noto‘g‘ri chiqadi.

  Verification: npm test — 208/208 passed; npm run lint passed; npm run build passed with the existing >500 kB
  chunk warning; git diff --check passed; working tree clean.

  VERDICT: CHANGES REQUESTED

## Seat downgrade, round 2

The `gpt-5.6-sol` weekly quota ran out after round 1. The full-lane primary seat for a Claude-built diff is `gpt-5.6-sol` at effort `high`; round 2 ran on `gpt-5.6-terra` at effort `medium`, the light-lane seat. Human ruled 2026-08-01: continue on terra rather than spend a usage reset or stop. Recall is lower than the seat table intends; weigh that when reading round 2's verdict.

## Round 2, gpt-5.6-terra (seat downgraded)

───────────────────────────────────────────────────────────────────────────────────────────────────────────────

• BLOCKER — Report totals still lose cents for large decimal-currency aggregates. mini-app/src/ui/Money.tsx:33
  now treats API amount as exact, but app/Support/Money.php:60 converts through a float. Live check:
  10000000000000001 USD minor units becomes "100000000000000.00" instead of "100000000000000.01". The new
  client tests use a fabricated exact string, so they do not cover the production contract. This requires an
  exact server decimal representation before the UI can meet the precision criterion.

  MAJOR — A slow stale “load more” request can permanently disable pagination for the newly selected filter.
  mini-app/src/history/useTransactions.ts:113 sets the global loadingMoreRef; switching filters advances the
  generation but does not release that guard. If the old request never settles, the new list may have a next
  cursor but every loadMore() returns immediately. The added race test resolves the stale request, so it misses
  this state.

  MAJOR — The newly added revisions request fails silently. mini-app/src/history/useRevisionCount.ts:21 turns
  both loading and failure into null, leaving the sheet with an indistinguishable placeholder and no retry,
  contrary to the mini-app error-handling requirement.

  Verified: mini-app tests 248/248, typecheck/lint, production build, revision endpoint focused backend tests,
  clean diff check, and clean worktree.

  VERDICT: CHANGES REQUESTED
