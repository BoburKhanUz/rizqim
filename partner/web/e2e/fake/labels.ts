// A branch's sticker and label settings of the fake backend (docs/API.md "Stiker va yorliq", P6-32). Answers like the server: the
// whole view with every key, only what is sent changes, `categories` needs categories, the labels module gates it (403
// module_disabled). Reached through FakeFormat (it owns the module switches).
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const SIZE_LABELS: Record<string, string> = { '40x30': '40 × 30 mm', '58x40': '58 × 40 mm' }
const SCOPE_LABELS: Record<string, string> = { all: 'Hamma taomlarga', categories: 'Tanlangan kategoriyalarga (masalan, ichimliklar)' }

export class FakeLabels {
  configured = false
  size = '40x30'
  scope = 'all'
  categoryIds: number[] = []
  perUnit = true
  bagLabel = true

  constructor(
    readonly backend: FakeBackend,
    private readonly moduleOn: () => boolean,
  ) {}

  private view(branchId: number) {
    return {
      branch_id: branchId,
      configured: this.configured,
      size: this.size,
      size_label: SIZE_LABELS[this.size],
      width_mm: Number(this.size.split('x')[0]),
      height_mm: Number(this.size.split('x')[1]),
      scope: this.scope,
      scope_label: SCOPE_LABELS[this.scope],
      category_ids: this.scope === 'categories' ? [...this.categoryIds].sort((a, b) => a - b) : [],
      per_unit: this.perUnit,
      bag_label: this.bagLabel,
      updated_at: this.configured ? '2026-11-20T10:00:00Z' : null,
    }
  }

  private update(branchId: number, body: Record<string, unknown>): Reply {
    const errors: Record<string, string[]> = {}
    if ('size' in body && !(String(body.size) in SIZE_LABELS)) errors.size = ["Yorliq o'lchami noto'g'ri."]
    if ('scope' in body && !(String(body.scope) in SCOPE_LABELS)) errors.scope = ["Stiker qaysi taomlarga noto'g'ri."]
    const scope = typeof body.scope === 'string' ? body.scope : this.scope
    const ids = Array.isArray(body.category_ids) ? (body.category_ids as number[]) : this.categoryIds
    if (scope === 'categories' && ids.length === 0) errors.category_ids = ['Kamida bitta kategoriya tanlang yoki "Hamma taomlarga"ni yoqing.']
    if (ids.some((id) => id === 999)) errors.category_ids = ['Kategoriya topilmadi.']
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)

    if (typeof body.size === 'string') this.size = body.size
    this.scope = scope
    this.categoryIds = scope === 'categories' ? ids : []
    if (typeof body.per_unit === 'boolean') this.perUnit = body.per_unit
    if (typeof body.bag_label === 'boolean') this.bagLabel = body.bag_label
    this.configured = true
    return ok(this.view(branchId))
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const match = /^\/v1\/owner\/branches\/(\d+)\/label-settings$/.exec(req.path)
    if (!match) return null
    if (!this.moduleOn()) return fail(403, "«Stakan stikeri va yorliq» moduli bu filialda o'chiq.", undefined, { reason: 'module_disabled', module: 'labels' })
    if (req.method === 'GET') return ok(this.view(Number(match[1])))
    if (req.method === 'PUT') return this.update(Number(match[1]), body ?? {})
    return null
  }
}
