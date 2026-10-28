import { NextResponse } from 'next/server';
import { realtimeConfigFromEnv } from '@/lib/realtime/config';

// GET /api/realtime: where the browser opens the Reverb socket (lib/realtime), read from the runtime environment so one
// image serves every environment. {config: null} turns real-time off (pages poll). Nothing secret: the app key is public.
export const dynamic = 'force-dynamic';

export function GET() {
  return NextResponse.json({ config: realtimeConfigFromEnv(process.env) }, { headers: { 'Cache-Control': 'no-store' } });
}
