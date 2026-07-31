// The single Uzbek string table for the mini app. Draft only: a native speaker reviews
// these before staff see them, same as the Telegram bot's strings (lang/uz/bot.php).
// Grows namespace by namespace as later tasks add the screens that need them.
export const strings = {
  session: {
    loading: 'Yuklanmoqda...',
    // Matches the bot's own wording for the same state (lang/uz/bot.php: pending).
    pending: "Siz hali ro'yxatdan o'tmagansiz. Administrator tasdiqlashini kuting.",
    refusedFallback: 'Kirish rad etildi.',
    errorFallback: "Nimadir xato ketdi. Qaytadan urinib ko'ring.",
  },
  tabs: {
    add: 'Kiritish',
    reports: 'Hisobotlar',
    history: 'Tarix',
  },
  common: {
    retry: 'Qaytadan urinish',
    save: 'Saqlash',
    delete: "O'chirish",
    confirm: 'Tasdiqlash',
    cancel: 'Bekor qilish',
  },
  entry: {
    amount: 'Summa',
    category: 'Turkum',
    details: 'Batafsil',
    type: 'Turi',
    expense: 'Xarajat',
    income: 'Daromad',
    currency: 'Valyuta',
    date: 'Sana',
    note: 'Izoh',
    backspace: "O'chirish",
    save: 'Saqlash',
    saved: 'Saqlandi.',
    undo: 'Bekor qilish',
    choose: 'Tanlang',
    // Shown when a 422 names `category_id` or `dimension_values`: the reference row was
    // deactivated since bootstrap loaded, so retrying the same pick cannot succeed.
    referenceChanged: "Ma'lumotlar yangilandi. Iltimos, qayta tanlang.",
    // Shown for any save failure that isn't a validation 422 (network, 500, malformed
    // body): the one signal the brief doesn't have a dedicated flow for, so it borrows
    // `notice` rather than leaving the tap looking like it did nothing at all.
    saveFailed: "Saqlashda xatolik yuz berdi. Qaytadan urinib ko'ring.",
  },
  reports: {
    period: 'Davr',
    thisMonth: 'Shu oy',
    lastMonth: "O'tgan oy",
    groupBy: 'Guruhlash',
    category: 'Turkum',
    interval: 'Oraliq',
    day: 'Kun',
    week: 'Hafta',
    month: 'Oy',
    // Shown instead of a chart with nothing in it: an empty period is a fact worth
    // stating plainly, not a blank rectangle the user has to interpret themselves.
    empty: "Bu davr uchun ma'lumot yo'q.",
    loadFailed: "Hisobotni yuklab bo'lmadi. Qaytadan urinib ko'ring.",
    // The report-view switch inside the Reports tab (Task 8): buttons inside the
    // Reports panel, not a second row of app-level tabs. `byStaff` only renders for a
    // user who may see others' spend — see `ReportsScreen`'s permission gate.
    view: "Ko'rinish",
    bySummary: 'Umumiy',
    byTrend: 'Dinamika',
    byStaff: 'Xodimlar',
  },
  history: {
    // Filter labels: `category`/`currency` reuse the same words as the entry form's
    // fields, since they name the same concepts; only the "all X" options below are new.
    category: 'Turkum',
    currency: 'Valyuta',
    allCategories: 'Barcha turkumlar',
    allCurrencies: 'Barcha valyutalar',
    allValues: 'Barchasi',
    empty: "Bu davr uchun yozuvlar yo'q.",
    loadFailed: "Tarixni yuklab bo'lmadi. Qaytadan urinib ko'ring.",
    deleteConfirm: "Haqiqatan ham o'chirmoqchimisiz?",
    // Shown for any save/delete failure that isn't a validation 422 — the server's own
    // `message` (a 403/404 refusal, or anything else) reaches the user verbatim rather
    // than being replaced with a client-side guess about why it failed.
    actionFailed: "Amalni bajarib bo'lmadi.",
  },
} as const
