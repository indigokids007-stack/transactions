import { createClient } from './client'

describe('createClient', () => {
  it('sends the bearer token on an authenticated call', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [] }), { status: 200 }),
    )
    const client = createClient('https://api.test', fetchMock)
    client.setToken('secret-token')

    await client.listTransactions({})

    const [, init] = fetchMock.mock.calls[0]
    expect(init.headers.Authorization).toBe('Bearer secret-token')
  })

  it('sends an idempotency key when creating', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: {} }), { status: 201 }),
    )
    const client = createClient('https://api.test', fetchMock)
    client.setToken('t')

    await client.createTransaction({ type: 'expense' }, 'key-1')

    const [, init] = fetchMock.mock.calls[0]
    expect(init.headers['Idempotency-Key']).toBe('key-1')
  })

  it('throws an ApiError carrying the status and the validation errors', async () => {
    const body = { message: 'invalid', errors: { amount: ['too big'] } }
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify(body), { status: 422 }),
    )
    const client = createClient('https://api.test', fetchMock)

    await expect(client.listTransactions({})).rejects.toMatchObject({
      status: 422,
      errors: { amount: ['too big'] },
    })
  })

  it('renders a dimension filter as a bracketed key, matching the API', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [] }), { status: 200 }),
    )
    const client = createClient('https://api.test', fetchMock)

    await client.listTransactions({ dimension: { branch: 9 } })

    const [url] = fetchMock.mock.calls[0]
    expect(String(url)).toContain('dimension%5Bbranch%5D=9')
  })

  it('does not send an Authorization header before a token is set', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [] }), { status: 200 }),
    )
    const client = createClient('https://api.test', fetchMock)

    await client.listTransactions({})

    const [, init] = fetchMock.mock.calls[0]
    expect(init.headers.Authorization).toBeUndefined()
  })

  // `TransactionRevisionResource::collection(...)` is a top-level controller return, so
  // — unlike `bootstrap()` — the response does carry Laravel's `data` wrapper
  // (`tests/Feature/Api/UpdateDeleteTransactionTest.php:217` asserts
  // `assertJsonCount(1, 'data')`). The client must unwrap it so a caller reads a plain
  // array, the same as every other list this client hands back.
  it('unwraps the revisions envelope into a plain array', async () => {
    const revision = { id: 9, action: 'updated', actor: { id: 1, name: 'A' }, snapshot: {}, created_at: 'x' }
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [revision] }), { status: 200 }),
    )
    const client = createClient('https://api.test', fetchMock)

    await expect(client.revisions(5)).resolves.toEqual([revision])

    const [url] = fetchMock.mock.calls[0]
    expect(String(url)).toContain('/api/transactions/5/revisions')
  })

  it('re-authenticates once on a 401 and retries the original call', async () => {
    // A `Response` body can only be read once, so a fresh instance is needed per call
    // even when two calls carry the same payload.
    const unauthorized = () => new Response(JSON.stringify({ message: 'Unauthenticated.' }), { status: 401 })
    const ok = () => new Response(JSON.stringify({ data: [] }), { status: 200 })
    const authOk = () =>
      new Response(JSON.stringify({ token: 'fresh-token', user: { id: 1 } }), { status: 200 })
    const fetchMock = vi
      .fn()
      // 1. the initial exchange, so the client learns the init data to retry with
      .mockResolvedValueOnce(authOk())
      // 2, 3. two concurrent calls made with a since-expired token
      .mockResolvedValueOnce(unauthorized())
      .mockResolvedValueOnce(unauthorized())
      // 4. the single re-authentication both callers share
      .mockResolvedValueOnce(authOk())
      // 5, 6. both original calls retried with the fresh token
      .mockResolvedValueOnce(ok())
      .mockResolvedValueOnce(ok())
    const client = createClient('https://api.test', fetchMock)
    await client.authenticate('init-data')
    client.setToken('stale-token')

    const [first, second] = await Promise.all([
      client.listTransactions({}),
      client.listTransactions({}),
    ])

    expect(first).toEqual({ data: [] })
    expect(second).toEqual({ data: [] })
    // 1 exchange + 2 failed calls + 1 re-authentication + 2 retried calls: one shared
    // re-auth, not one per caller.
    expect(fetchMock).toHaveBeenCalledTimes(6)
    const authCall = fetchMock.mock.calls[3]
    expect(String(authCall[0])).toContain('/api/auth/telegram')
  })
})
