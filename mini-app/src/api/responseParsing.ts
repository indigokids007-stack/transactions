export async function readBody(response: Response): Promise<unknown> {
  const text = await response.text()
  if (text === '') return undefined
  return JSON.parse(text) as unknown
}

export function extractMessage(body: unknown, fallback: string): string {
  if (typeof body === 'object' && body !== null && 'message' in body) {
    const message = (body as { message?: unknown }).message
    if (typeof message === 'string') return message
  }
  return fallback
}

export function extractErrors(body: unknown): Record<string, string[]> | undefined {
  if (typeof body === 'object' && body !== null && 'errors' in body) {
    const errors = (body as { errors?: unknown }).errors
    if (typeof errors === 'object' && errors !== null) {
      return errors as Record<string, string[]>
    }
  }
  return undefined
}
