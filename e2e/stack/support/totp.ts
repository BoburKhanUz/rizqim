// TOTP (RFC 6238: HMAC-SHA1, 30 s steps, 6 digits), the way Google Authenticator computes it, from the base32 key the control
// center shows next to its QR code. The test is the "phone".
import { createHmac } from 'node:crypto'

const STEP_SECONDS = 30
const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'

export function base32Decode(text: string): Buffer {
  let bits = ''
  for (const char of text.replace(/[\s=-]/g, '').toUpperCase()) {
    const value = ALPHABET.indexOf(char)
    if (value < 0) throw new Error(`Not a base32 key: ${text}`)
    bits += value.toString(2).padStart(5, '0')
  }
  const bytes: number[] = []
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(Number.parseInt(bits.slice(i, i + 8), 2))
  return Buffer.from(bytes)
}

export const stepOf = (at = Date.now()): number => Math.floor(at / 1000 / STEP_SECONDS)

export function codeAt(secret: string, step: number): string {
  const counter = Buffer.alloc(8)
  counter.writeBigUInt64BE(BigInt(step))
  const digest = createHmac('sha1', base32Decode(secret)).update(counter).digest()
  const offset = digest[digest.length - 1]! & 0x0f
  const binary = ((digest[offset]! & 0x7f) << 24) | (digest[offset + 1]! << 16) | (digest[offset + 2]! << 8) | digest[offset + 3]!
  return String(binary % 1_000_000).padStart(6, '0')
}

// One "phone" with one secret. The backend takes every code once and nothing older after it, so `next()` waits for a time
// step later than the last one it gave out (and not for the last seconds of a step, where the server's clock could already be
// in the next one).
export class Authenticator {
  private last = -1

  constructor(readonly secret: string) {}

  /** The code of the current step, once per step. */
  async next(): Promise<string> {
    for (;;) {
      const now = Date.now()
      const step = stepOf(now)
      const secondsLeft = STEP_SECONDS - ((now / 1000) % STEP_SECONDS)
      if (step > this.last && secondsLeft > 3) {
        this.last = step
        return codeAt(this.secret, step)
      }
      await new Promise((resolve) => setTimeout(resolve, 500))
    }
  }

  /** The code of the step that was already used: the backend must refuse it. */
  usedCode(): string {
    if (this.last < 0) throw new Error('No code has been given out yet')
    return codeAt(this.secret, this.last)
  }
}
