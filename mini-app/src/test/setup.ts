import '@testing-library/jest-dom/vitest'

// jsdom doesn't implement IntersectionObserver (still true as of jsdom 30), and
// `TransactionList`'s infinite-scroll effect constructs one whenever `hasMore` is true.
// A minimal stub is enough: nothing under test asserts on real viewport intersection,
// only on `loadMore` being called (at the `useTransactions` level) or not (because
// `hasMore` is false, so the constructor is never reached at all).
class IntersectionObserverStub {
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
}

if (typeof globalThis.IntersectionObserver === 'undefined') {
  globalThis.IntersectionObserver = IntersectionObserverStub as unknown as typeof IntersectionObserver
}
