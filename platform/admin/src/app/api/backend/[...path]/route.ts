import type { NextRequest } from 'next/server';
import { forward } from '@/lib/server/proxy';

// /api/backend/<backend path under /api>: the page's only way to the backend (lib/server/proxy.ts). Never cached.
export const dynamic = 'force-dynamic';

type Context = { params: Promise<{ path: string[] }> };

const handle = async (request: NextRequest, context: Context) => forward(request, (await context.params).path);

export const GET = handle;
export const POST = handle;
export const PUT = handle;
export const PATCH = handle;
export const DELETE = handle;
