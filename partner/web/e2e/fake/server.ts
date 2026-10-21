// The HTTP face of the fake backend: the Next server's route handlers (server side) and the page (browser, another origin,
// so with CORS) both call it. It also serves map tiles (a 1x1 picture), so the zone editor needs no internet.
import { createServer, type IncomingMessage, type Server, type ServerResponse } from 'node:http'
import { Readable } from 'node:stream'
import { FakeBackend } from './backend'
import type { FakeRequest } from './types'

// A transparent 1x1 PNG
const TILE = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64')

export class FakeServer {
  backend = new FakeBackend()
  private server: Server | null = null
  /** Milliseconds every API answer is held back (a slow network), 0 normally. */
  latencyMs = 0

  /** A clean backend for the next test (the accounts, orders and menu of the start). */
  reset(): FakeBackend {
    this.backend = new FakeBackend()
    this.latencyMs = 0
    return this.backend
  }

  async start(port: number): Promise<void> {
    this.server = createServer((req, res) => {
      this.serve(req, res).catch((error: unknown) => {
        res.writeHead(500, { 'Content-Type': 'application/json' }).end(JSON.stringify({ message: `E2E fake server error: ${String(error)}` }))
      })
    })
    await new Promise<void>((resolve, reject) => {
      this.server!.once('error', reject)
      this.server!.listen(port, '127.0.0.1', resolve)
    })
  }

  async stop(): Promise<void> {
    const server = this.server
    this.server = null
    if (!server) return
    server.closeAllConnections()
    await new Promise<void>((resolve) => server.close(() => resolve()))
  }

  private cors(req: IncomingMessage, res: ServerResponse): void {
    const origin = req.headers.origin
    if (origin) {
      res.setHeader('Access-Control-Allow-Origin', origin)
      res.setHeader('Vary', 'Origin')
      res.setHeader('Access-Control-Allow-Headers', 'authorization, content-type, accept, idempotency-key, x-requested-with')
      res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
    }
  }

  private async serve(req: IncomingMessage, res: ServerResponse): Promise<void> {
    this.cors(req, res)
    const url = new URL(req.url ?? '/', 'http://fake.test')

    if (req.method === 'OPTIONS') {
      res.writeHead(204).end()
      return
    }
    if (url.pathname.startsWith('/tiles/')) {
      res.writeHead(200, { 'Content-Type': 'image/png', 'Cache-Control': 'max-age=3600' }).end(TILE)
      return
    }
    if (!url.pathname.startsWith('/api/')) {
      res.writeHead(404, { 'Content-Type': 'application/json' }).end(JSON.stringify({ message: 'not an API path' }))
      return
    }

    const contentType = req.headers['content-type'] ?? ''
    let body: FakeRequest['body'] = null
    if (req.method !== 'GET' && req.method !== 'HEAD' && req.method !== 'DELETE') {
      const headers = new Headers()
      for (const [name, value] of Object.entries(req.headers)) if (typeof value === 'string') headers.set(name, value)
      const request = new Request(url, { method: req.method, headers, body: Readable.toWeb(req) as ReadableStream, duplex: 'half' } as RequestInit)
      if (contentType.startsWith('multipart/form-data')) body = await request.formData()
      else if (contentType.startsWith('application/x-www-form-urlencoded')) body = Object.fromEntries(new URLSearchParams(await request.text()))
      else {
        const text = await request.text()
        body = text ? (JSON.parse(text) as Record<string, unknown>) : null
      }
    }

    if (this.latencyMs > 0) await new Promise((resolve) => setTimeout(resolve, this.latencyMs))

    const reply = this.backend.handle({
      method: req.method ?? 'GET',
      path: url.pathname.replace(/^\/api/, ''),
      query: Object.fromEntries(url.searchParams),
      body,
      authorization: req.headers.authorization ?? null,
    })
    const headers = { 'Content-Type': 'application/json', ...reply.headers }
    res.writeHead(reply.status, headers).end(reply.raw ?? JSON.stringify(reply.body))
  }
}
