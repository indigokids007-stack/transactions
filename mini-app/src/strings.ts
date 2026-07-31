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
    // Not a tab of its own: the staff comparison lives inside the Reports tab, gated by
    // permission (see the design doc and Task 8). Kept here only because the shell's
    // test asserts no tab ever carries this name, whatever the caller's permissions.
    staff: 'Xodimlar',
  },
  common: {
    retry: 'Qaytadan urinish',
  },
} as const
