// Makes ONE real POS order (open -> round -> send -> bill -> cash -> close) and ONE Mini App order on the seeded
// template store through the API; gen.sql copies their row shapes. Usage: node template_order.mjs seed.json
import fs from 'fs';
const seed = JSON.parse(fs.readFileSync(process.argv[2]));
const B = `http://127.0.0.1:${process.env.APP_PORT || 18500}/api/v1`;
const r = async (m, p, tok, body) => {
  const res = await fetch(B + p, { method: m, headers: { Authorization: 'Bearer ' + tok, Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': crypto.randomUUID() }, body: body ? JSON.stringify(body) : undefined });
  const j = await res.json().catch(() => ({})); if (res.status >= 300) throw new Error(`${m} ${p} ${res.status} ${JSON.stringify(j).slice(0, 300)}`); return j.data;
};
const V = process.argv[3].split(',');
const term = seed.terminals.cashier;
const st = (await r('POST', `/devices/${term.device.uuid}/staff-session`, term.token, { user_id: seed.staff.cashier.id, pin: seed.staff.cashier.pin })).token;
await r('POST', '/pos/shifts', st, { opening_cash: 100000 });
const o = await r('POST', '/pos/orders', st, { channel: 'pos_takeaway', items: [{ variant_id: V[0], quantity: 2 }, { variant_id: V[1], quantity: 1 }] });
const o2 = await r('POST', `/pos/orders/${o.uuid}/rounds`, st, { version: o.version, items: [{ variant_id: V[2], quantity: 1 }] });
const o3 = await r('POST', `/pos/orders/${o.uuid}/send`, st, { version: o2.version });
const b = (await r('POST', `/pos/orders/${o.uuid}/bills`, st, { version: o3.version, mode: 'single' })).bills[0];
const p = await r('POST', `/pos/bills/${b.uuid}/payments`, st, { tenders: [{ tender: 'cash', amount: b.total, tendered_amount: b.total }] });
await r('POST', `/pos/orders/${o.uuid}/close`, st, { version: p.order.version });
const m = await r('POST', '/orders', seed.customer.token, { store_id: seed.store.id, type: 'pickup', branch_id: seed.branch.id, payment_method: 'cash', items: [{ variant_id: V[0], quantity: 1 }] });
console.log(JSON.stringify({ pos_order_uuid: o.uuid, mkt_order_uuid: m.order_uuid }));
