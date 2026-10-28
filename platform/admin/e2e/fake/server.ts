// The HTTP face of the fake admin backend: the Next server's proxy calls it.
import { createServer, type IncomingMessage, type Server, type ServerResponse } from 'node:http'
import { FakeBackend } from './backend'
import type { FakeRequest } from './types'

export class FakeServer {
  backend = new FakeBackend()
  private server: Server | null = null
  /** Milliseconds every answer is held back (a slow network), 0 normally. */
  latencyMs = 0

  /** A clean backend for the next test. */
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

  private async serve(req: IncomingMessage, res: ServerResponse): Promise<void> {
    const url = new URL(req.url ?? '/', 'http://fake.test')
    if (!url.pathname.startsWith('/api/')) {
      res.writeHead(404, { 'Content-Type': 'application/json' }).end(JSON.stringify({ message: 'not an API path' }))
      return
    }
    let text = ''
    for await (const chunk of req) text += String(chunk)
    const body = text ? (JSON.parse(text) as Record<string, unknown>) : null
    if (this.latencyMs > 0) await new Promise((resolve) => setTimeout(resolve, this.latencyMs))

    const request: FakeRequest = {
      method: req.method ?? 'GET',
      path: url.pathname.replace(/^\/api/, ''),
      query: Object.fromEntries(url.searchParams),
      body,
      authorization: req.headers.authorization ?? null,
    }
    const reply = this.backend.handle(request)
    res.writeHead(reply.status, { 'Content-Type': 'application/json', ...reply.headers }).end(JSON.stringify(reply.body ?? null))
  }
}
