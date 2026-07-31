// The single Uzbek string table for the mini app. Draft only: a native speaker reviews
// these before staff see them, same as the Telegram bot's strings (lang/uz/bot.php).
// Grows namespace by namespace as later tasks add the screens that need them.
export const strings = {
  session: {
    // Matches the bot's own wording for the same state (lang/uz/bot.php: pending).
    pending: "Siz hali ro'yxatdan o'tmagansiz. Administrator tasdiqlashini kuting.",
    refusedFallback: 'Kirish rad etildi.',
    errorFallback: "Nimadir xato ketdi. Qaytadan urinib ko'ring.",
  },
} as const
