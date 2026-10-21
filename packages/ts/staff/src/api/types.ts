import type { operations } from '@rizqim/api-client';

// Request and response bodies of an operation of the spec (operations["order.index"], ...): the screens and hooks name
// the types they need from the generated ones instead of writing them again.

type ResponseOf<Op extends keyof operations, Status extends number> = operations[Op]['responses'] extends infer R
  ? Status extends keyof R
    ? R[Status]
    : never
  : never;

export type JsonBody<Response> = Response extends { content: { 'application/json': infer Body } } ? Body : never;

// The `data` of a 200 (or other status) answer of an operation.
export type OkData<Op extends keyof operations, Status extends number = 200> =
  JsonBody<ResponseOf<Op, Status>> extends { data: infer Data } ? Data : never;

// The JSON body an operation takes.
export type JsonRequest<Op extends keyof operations> = operations[Op] extends { requestBody?: infer Body }
  ? Body extends { content: { 'application/json': infer Json } }
    ? Json
    : never
  : never;
