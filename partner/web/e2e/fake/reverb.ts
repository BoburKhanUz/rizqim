// A stand-in for Laravel Reverb at the WebSocket level (the Pusher protocol): the page's real pusher-js and laravel-echo
// connect to it, sign their private subscriptions through the fake API's /broadcasting/auth, and the test pushes events.
// No socket is opened on the machine: Playwright answers the page's WebSocket itself (page.routeWebSocket).
import type { Page, WebSocketRoute } from '@playwright/test'

interface Connection {
  socket: WebSocketRoute
  channels: Set<string>
}

export class FakeReverb {
  private connections: Connection[] = []
  /** Every channel any connection subscribed to, in order (a test asserts which channels the board listens on). */
  readonly subscribed: string[] = []
  private refuse = false

  /** Makes the socket fail from now on (a connection that is closed straight away), and drops the open ones. */
  breakDown(): void {
    this.refuse = true
    for (const connection of this.connections) connection.socket.close()
    this.connections = []
  }

  get connected(): boolean {
    return this.connections.length > 0
  }

  /** Delivers an event to every connection subscribed to the channel, as Reverb would after the backend broadcast it. */
  emit(channel: string, event: string, payload: unknown): void {
    for (const connection of this.connections) {
      if (connection.channels.has(channel)) connection.socket.send(JSON.stringify({ event, channel, data: JSON.stringify(payload) }))
    }
  }

  /** The order events of docs/API.md 8: {order: {uuid, status, store_id, branch_id, courier_id, updated_at}}. */
  emitOrder(event: 'order.created' | 'order.status_changed' | 'order.assigned', order: { uuid: string; status: string; branch_id: number }, channels: string[]): void {
    const payload = { order: { uuid: order.uuid, status: order.status, store_id: 1, branch_id: order.branch_id, courier_id: null, updated_at: new Date().toISOString() } }
    for (const channel of channels) this.emit(channel, event, payload)
  }

  async install(page: Page): Promise<void> {
    await page.routeWebSocket(/\/app\//, (socket) => {
      if (this.refuse) {
        socket.close()
        return
      }
      const connection: Connection = { socket, channels: new Set() }
      this.connections.push(connection)
      socket.onClose(() => {
        this.connections = this.connections.filter((candidate) => candidate !== connection)
      })
      socket.onMessage((message) => {
        const frame = JSON.parse(String(message)) as { event: string; data?: { channel?: string } }
        if (frame.event === 'pusher:subscribe' && frame.data?.channel) {
          connection.channels.add(frame.data.channel)
          this.subscribed.push(frame.data.channel)
          socket.send(JSON.stringify({ event: 'pusher_internal:subscription_succeeded', channel: frame.data.channel, data: '{}' }))
        } else if (frame.event === 'pusher:ping') {
          socket.send(JSON.stringify({ event: 'pusher:pong', data: '{}' }))
        }
      })
      socket.send(JSON.stringify({ event: 'pusher:connection_established', data: JSON.stringify({ socket_id: `1234.${this.connections.length}`, activity_timeout: 120 }) }))
    })
  }
}
