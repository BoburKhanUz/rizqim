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
    const chunks: Buffer[] = []
    for await (const chunk of req) chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(String(chunk)))
    const bytes = Buffer.concat(chunks)
    const contentType = String(req.headers['content-type'] ?? '')
    let body: Record<string, unknown> | null = null
    if (/^multipart\/form-data/i.test(contentType)) {
      // P4-23: a file upload: its fields, and each file as {name, size, type}
      const form = await new Request('http://fake.test/', { method: 'POST', headers: { 'Content-Type': contentType }, body: bytes }).formData()
      body = {}
      for (const [name, value] of form.entries()) body[name] = typeof value === 'string' ? value : { name: value.name, size: value.size, type: value.type }
    } else if (bytes.length > 0) {
      body = JSON.parse(bytes.toString('utf8')) as Record<string, unknown>
    }
    if (this.latencyMs > 0) await new Promise((resolve) => setTimeout(resolve, this.latencyMs))

    const request: FakeRequest = {
      method: req.method ?? 'GET',
      path: url.pathname.replace(/^\/api/, ''),
      query: Object.fromEntries(url.searchParams),
      body,
      authorization: req.headers.authorization ?? null,
      headers: Object.fromEntries(Object.entries(req.headers).filter((entry): entry is [string, string] => typeof entry[1] === 'string')),
    }
    const reply = this.backend.handle(request)
    if (reply.raw) {
      res.writeHead(reply.status, reply.headers ?? {}).end(Buffer.from(reply.raw))
      return
    }
    res.writeHead(reply.status, { 'Content-Type': 'application/json', ...reply.headers }).end(JSON.stringify(reply.body ?? null))
  }
}
