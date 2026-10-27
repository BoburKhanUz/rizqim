import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { forgetAcceptedCode } from '@/lib/api/usedCodes';
import { createPlatformOps, setPlatformOps } from '@/lib/realtime';
import { FakeEcho } from '@/test/fakeEcho';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { MonitoringView } from './MonitoringView';

const minutesAgo = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString();

const VIEW = ['admin.monitoring.view'];
const MANAGE = ['admin.monitoring.view', 'admin.monitoring.manage'];

const health = (overrides: Record<string, unknown> = {}) => ({
  status: 'degraded',
  checked_at: minutesAgo(0),
  database: { status: 'ok', latency_ms: 1.4 },
  redis: { status: 'not_used' },
  queue: {
    status: 'backlog',
    queues: [
      { name: 'default', size: 640, oldest_age_seconds: 780 },
      { name: 'broadcasts', size: 0, oldest_age_seconds: null },
      { name: 'reports', size: null, oldest_age_seconds: null },
    ],
    failed_jobs: { total: 3, last_24h: 2, latest_failed_at: minutesAgo(12) },
  },
  scheduler: { status: 'ok', last_heartbeat_age_seconds: 20, max_age_seconds: 180 },
  reverb: { status: 'not_configured', latency_ms: null },
  backup: { status: 'stale', last_backup_at: minutesAgo(30 * 60), age_hours: 30, max_age_hours: 26 },
  app: { version: '1.4.0', commit: 'a1b2c3d', environment: 'production' },
  sentry: { configured: true, environment: 'production', project_url: 'https://sentry.io/organizations/rizqim/projects/rizqim-backend/' },
  alerts: { open: 2, acknowledged: 1, critical_unresolved: 1 },
  ...overrides,
});

const alert = (overrides: Record<string, unknown> = {}) => ({
  id: 1,
  kind: 'backup_stale',
  kind_label: 'Zaxira nusxa eskirgan',
  severity: 'critical',
  state: 'open',
  title: 'Oxirgi zaxira nusxa 30 soat oldin olingan',
  details: { last_backup_at: minutesAgo(1800), age_hours: 30, threshold_hours: 26 },
  store: null,
  first_seen_at: minutesAgo(240),
  last_seen_at: minutesAgo(1),
  count: 240,
  acknowledged: null,
  resolved: null,
  ...overrides,
});

const ALERTS = [
  alert(),
  alert({ id: 2, kind: 'queue_backlog', kind_label: "Navbat to'planishi", severity: 'warning', title: '"default" navbatida 640 ta vazifa to\'planib qoldi', details: { queue: 'default', size: 640, oldest_age_seconds: 780 } }),
  alert({
    id: 3,
    kind: 'pos_device_offline',
    kind_label: 'Ish vaqtida oflayn kassa',
    severity: 'warning',
    state: 'acknowledged',
    title: 'Lazzat Oshxona: Kassa 1 45 daqiqadan beri aloqada emas',
    store: { id: 12, name: 'Lazzat Oshxona' },
    details: { store: 'Lazzat Oshxona', offline_minutes: 45 },
    acknowledged: { by: { id: 2, name: 'Sardor Operator' }, at: minutesAgo(30), note: "Egasiga qo'ng'iroq qildim" },
  }),
];

const alertsAnswer = (data: unknown[] = ALERTS, counts = { open: 2, acknowledged: 1, resolved_7d: 4 }) => ok({ data, next_cursor: null, prev_cursor: null, per_page: 25, counts });

const job = (overrides: Record<string, unknown> = {}) => ({
  id: 3,
  uuid: '00000000-0000-4000-8000-000000000003',
  job: 'SendTelegramRequest',
  job_class: 'App\\Jobs\\SendTelegramRequest',
  queue: 'default',
  connection: 'redis',
  exception_line: 'ConnectionException: cURL error 28 for https://api.telegram.org/bot[Filtered]/sendMessage',
  failed_at: minutesAgo(12),
  ...overrides,
});

const JOB_UUID = '00000000-0000-4000-8000-000000000003';
const jobsAnswer = (data: unknown[] = [job(), job({ id: 2, uuid: '00000000-0000-4000-8000-000000000002', job: 'SubmitClickFiscalData', queue: 'reports', exception_line: 'RuntimeException: Click fiscal API returned 502' })], total = 2) =>
  ok({ data, next_cursor: null, prev_cursor: null, per_page: 25, total });

const detail = () =>
  ok({
    ...job(),
    attempts: 5,
    max_tries: 5,
    exception: 'ConnectionException: cURL error 28 for https://api.telegram.org/bot[Filtered]/sendMessage\n#0 {main}',
    payload: { displayName: 'App\\Jobs\\SendTelegramRequest', command: { method: 'sendMessage', params: { api_key: '[redacted]', chat_id: '-100123' } } },
  });

const rule = (overrides: Record<string, unknown> = {}) => ({
  kind: 'queue_backlog',
  label: "Navbat to'planishi",
  description: "Navbatda vazifalar to'planib qoldi yoki eng eskisi juda uzoq kutmoqda.",
  severity: 'warning',
  enabled: true,
  telegram: true,
  threshold: 500,
  threshold_secondary: 10,
  window_minutes: null,
  fields: {
    threshold: { label: 'Navbatdagi vazifalar soni', unit: 'ta', min: 1, max: 1000000 },
    threshold_secondary: { label: 'Eng eski vazifa kutish vaqti', unit: 'daqiqa', min: 1, max: 1440 },
    window: null,
  },
  defaults: { threshold: 500, threshold_secondary: 10, window_minutes: null },
  unresolved_alerts: 1,
  updated_at: null,
  updated_by: null,
  ...overrides,
});

const RULES = [
  rule(),
  rule({
    kind: 'failed_jobs_spike',
    label: "Yiqilgan vazifalar ko'payishi",
    description: 'Oxirgi daqiqalarda yiqilgan fon vazifalari soni chegaradan oshdi.',
    threshold: 10,
    threshold_secondary: null,
    window_minutes: 15,
    fields: {
      threshold: { label: 'Yiqilgan vazifalar soni', unit: 'ta', min: 1, max: 10000 },
      threshold_secondary: null,
      window: { label: 'Oxirgi qancha vaqt ichida', unit: 'daqiqa', min: 1, max: 1440 },
    },
    defaults: { threshold: 10, threshold_secondary: null, window_minutes: 15 },
    unresolved_alerts: 0,
  }),
];

function setup(extra: Parameters<typeof mockFetch>[0] = {}, permissions = MANAGE, tab: Parameters<typeof MonitoringView>[0] = {}) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /monitoring/health': () => ok(health()),
    'GET /monitoring/alerts': () => alertsAnswer(),
    'GET /monitoring/failed-jobs': () => jobsAnswer(),
    'GET /monitoring/rules': () => ok({ rules: RULES, telegram_configured: true }),
    ...extra,
  });
  renderPage(<MonitoringView {...tab} />);
  return { ...mocks, user: userEvent.setup() };
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

// Real-time is off unless a test turns it on (the shared connection would ask GET /api/realtime)
function realtime(echo: FakeEcho | null) {
  setPlatformOps(createPlatformOps({ loadConfig: async () => (echo ? { key: 'k', host: 'h', port: 443, forceTLS: true } : null), createEcho: async () => echo ?? Promise.reject(new Error('off')) }));
}

beforeEach(() => {
  window.history.replaceState(null, '', '/monitoring');
  realtime(null);
});
afterEach(() => {
  forgetAcceptedCode();
  setPlatformOps(null);
});

describe('Monitoring: Holat', () => {
  it('says each part of the system in words, with numbers', async () => {
    setup({}, VIEW);
    expect(await screen.findByText("E'tibor kerak", { selector: 'p' })).toBeInTheDocument();
    expect(screen.getByText('Ilova ishlayapti, lekin quyidagilarga qarash kerak.')).toBeInTheDocument();

    const tile = (name: string) => within(screen.getByRole('heading', { name }).closest('li') as HTMLElement);
    expect(tile("Ma'lumotlar bazasi").getByText('Ishlayapti')).toBeInTheDocument();
    expect(tile("Ma'lumotlar bazasi").getByText('Javob vaqti: 1.4 ms')).toBeInTheDocument();
    expect(tile('Redis').getByText('Ishlatilmaydi')).toBeInTheDocument();

    const queues = tile('Navbatlar');
    expect(queues.getByText("To'planib qolgan")).toBeInTheDocument();
    expect(queues.getByText('«default»: 640 ta, eng eskisi 13 daqiqa kutmoqda')).toBeInTheDocument();
    expect(queues.getByText("«broadcasts»: bo'sh")).toBeInTheDocument();
    expect(queues.getByText("«reports»: o'qib bo'lmadi")).toBeInTheDocument();

    expect(tile('Scheduler').getByText('Oxirgi yurak urishi 20 soniya oldin')).toBeInTheDocument();
    expect(tile('Real-time (Reverb)').getByText("O'chirilgan")).toBeInTheDocument();
    expect(tile('Zaxira nusxa').getByText('Eskirgan')).toBeInTheDocument();
    expect(tile('Zaxira nusxa').getByText(/Oxirgi nusxa 1 kun oldin olingan, bu 26 soatdan eski/)).toBeInTheDocument();
    expect(tile('Yiqilgan vazifalar').getByText('3 ta, oxirgi 24 soatda 2 ta')).toBeInTheDocument();
    expect(tile('Ogohlantirishlar').getByText('2 ta ochiq, 1 ta qabul qilingan')).toBeInTheDocument();
    expect(tile('Ogohlantirishlar').getByText('1 tasi kritik')).toBeInTheDocument();
    expect(tile('Ilova').getByText('1.4.0')).toBeInTheDocument();
    expect(tile('Ilova').getByText('a1b2c3d')).toBeInTheDocument();
  });

  it('links to the Sentry project and says so when none is set', async () => {
    setup({}, VIEW);
    const link = await screen.findByRole('link', { name: /Sentry'da ochish/ });
    expect(link).toHaveAttribute('href', 'https://sentry.io/organizations/rizqim/projects/rizqim-backend/');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('shows no Sentry link when the project is not configured, and refuses a non-https one', async () => {
    setup({ 'GET /monitoring/health': () => ok(health({ sentry: { configured: false, environment: null, project_url: 'javascript:alert(1)' } })) }, VIEW);
    expect(await screen.findByText("Ulanmagan (SENTRY_LARAVEL_DSN bo'sh)")).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Sentry/ })).toBeNull();
    expect(screen.getByText('Sentry loyihasi havolasi sozlanmagan (SENTRY_PROJECT_URL).')).toBeInTheDocument();
  });

  it('shows a calm all-clear and a failure in words, not only in colour', async () => {
    setup({
      'GET /monitoring/health': () =>
        ok(health({ status: 'ok', queue: { status: 'ok', queues: [], failed_jobs: { total: 0, last_24h: 0, latest_failed_at: null } }, backup: { status: 'ok', last_backup_at: minutesAgo(180), age_hours: 3, max_age_hours: 26 }, reverb: { status: 'ok', latency_ms: 4.2 }, alerts: { open: 0, acknowledged: 0, critical_unresolved: 0 } })),
    }, VIEW);
    expect(await screen.findByText('Hammasi joyida', { selector: 'p' })).toBeInTheDocument();
    expect(screen.getByText("Yiqilgan vazifa yo'q")).toBeInTheDocument();
    expect(screen.getByText("Ochiq ogohlantirish yo'q")).toBeInTheDocument();
    expect(screen.getByText('Ulanish bor (4.2 ms)')).toBeInTheDocument();
  });

  it('says when the database is down', async () => {
    setup({ 'GET /monitoring/health': () => ok(health({ status: 'fail', database: { status: 'fail' } })) }, VIEW);
    expect(await screen.findByText('Tizim ishlamayapti', { selector: 'p' })).toBeInTheDocument();
    const tile = within(screen.getByRole('heading', { name: "Ma'lumotlar bazasi" }).closest('li') as HTMLElement);
    expect(tile.getByText('Ishlamayapti')).toBeInTheDocument();
    expect(tile.getByText('Baza javob bermayapti. Ilova ishlay olmaydi.')).toBeInTheDocument();
  });

  it('says a failed load in place and tries again on request', async () => {
    let calls = 0;
    const { user } = setup({ 'GET /monitoring/health': () => (++calls === 1 ? refusal(500, 'Server xatosi.') : ok(health())) }, VIEW);
    expect(await screen.findByText('Holat yuklanmadi')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await screen.findByText("E'tibor kerak", { selector: 'p' })).toBeInTheDocument();
  });

  it('refuses a member without monitoring.view', async () => {
    const { callsTo } = setup({}, ['admin.today.view']);
    expect(await screen.findByText("Monitoringni ko'rish uchun ruxsat kerak.")).toBeInTheDocument();
    expect(callsTo('GET', '/monitoring/health')).toHaveLength(0);
  });

  it('moves to the alerts and failed jobs tabs from its tiles, and keeps the address in step', async () => {
    const { user } = setup({}, VIEW);
    await screen.findByText("E'tibor kerak", { selector: 'p' });
    await user.click(within(screen.getByRole('heading', { name: 'Ogohlantirishlar' }).closest('li') as HTMLElement).getByRole('button', { name: "Ko'rish" }));
    expect(await screen.findByRole('list', { name: "Ogohlantirishlar ro'yxati" })).toBeInTheDocument();
    expect(window.location.search).toBe('?tab=alerts');
    expect(screen.getByRole('tab', { name: 'Ogohlantirishlar (3)' })).toHaveAttribute('aria-selected', 'true');
  });
});

describe('Monitoring: Ogohlantirishlar', () => {
  it('lists the open alerts first with who took the others, and expands the details in words', async () => {
    const { user } = setup({}, MANAGE, { initialTab: 'alerts' });
    const list = await screen.findByRole('list', { name: "Ogohlantirishlar ro'yxati" });
    const cards = within(list).getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent);
    expect(cards).toEqual(['Oxirgi zaxira nusxa 30 soat oldin olingan', '"default" navbatida 640 ta vazifa to\'planib qoldi', 'Lazzat Oshxona: Kassa 1 45 daqiqadan beri aloqada emas']);

    const first = within(list.children[0] as HTMLElement);
    expect(first.getByText('Kritik')).toBeInTheDocument();
    expect(first.getByText('Ochiq')).toBeInTheDocument();
    expect(first.getByText(/240 marta tekshiruvda ko'rindi/)).toBeInTheDocument();

    const third = within(list.children[2] as HTMLElement);
    expect(third.getByText('Qabul qilingan')).toBeInTheDocument();
    expect(third.getByText(/Qabul qildi: Sardor Operator/)).toBeInTheDocument();
    expect(third.getByText(/Izoh: Egasiga qo'ng'iroq qildim/)).toBeInTheDocument();
    expect(third.getByText('Ovqatxona: Lazzat Oshxona')).toBeInTheDocument();
    expect(third.queryByRole('button', { name: /Qabul qilish/ })).toBeNull();

    await user.click(within(list.children[1] as HTMLElement).getByRole('button', { name: 'Tafsilot' }));
    expect(screen.getByText('Navbatdagi vazifalar')).toBeInTheDocument();
    expect(screen.getByText('Eng eskisi kutmoqda').nextSibling).toHaveTextContent('13 daqiqa');
  });

  it('acknowledges with a note', async () => {
    let body: unknown = null;
    const { user, callsTo } = setup({ 'POST /monitoring/alerts/1/acknowledge': (request) => ((body = request), ok({ id: 1, kind: 'backup_stale', state: 'acknowledged' })) }, MANAGE, { initialTab: 'alerts' });
    await user.click(await screen.findByRole('button', { name: 'Qabul qilish: Oxirgi zaxira nusxa 30 soat oldin olingan' }));
    const dialog = screen.getByRole('dialog', { name: 'Ogohlantirishni qabul qilasizmi?' });
    await user.type(within(dialog).getByLabelText('Izoh (ixtiyoriy)'), 'Serverga kirdim');
    await user.click(within(dialog).getByRole('button', { name: 'Qabul qilish' }));

    expect(await screen.findByText('Ogohlantirish qabul qilindi.')).toBeInTheDocument();
    expect(callsTo('POST', '/monitoring/alerts/1/acknowledge')[0]?.body).toEqual({ note: 'Serverga kirdim' });
    expect(body).not.toBeNull();
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('resolves without a note, and sends none', async () => {
    const { user, callsTo } = setup({ 'POST /monitoring/alerts/2/resolve': () => ok({ id: 2, kind: 'queue_backlog', state: 'resolved' }) }, MANAGE, { initialTab: 'alerts' });
    await user.click(await screen.findByRole('button', { name: /^Hal qilish: "default" navbatida/ }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Hal qilish' }));

    expect(await screen.findByText('Ogohlantirish hal qilindi.')).toBeInTheDocument();
    expect(callsTo('POST', '/monitoring/alerts/2/resolve')[0]?.body).toEqual({});
  });

  it('refuses a one-character note before asking the server', async () => {
    const { user, callsTo } = setup({}, MANAGE, { initialTab: 'alerts' });
    await user.click(await screen.findByRole('button', { name: /^Qabul qilish: Oxirgi zaxira/ }));
    await user.type(screen.getByLabelText('Izoh (ixtiyoriy)'), 'x');
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Qabul qilish' }));

    expect(await screen.findByText("Izoh kamida 2 belgi bo'lsin yoki bo'sh qoldiring.")).toBeInTheDocument();
    expect(callsTo('POST', '/monitoring/alerts/1/acknowledge')).toHaveLength(0);
  });

  it('says it when someone else took the alert first, in the dialog', async () => {
    const { user } = setup({ 'POST /monitoring/alerts/1/acknowledge': () => refusal(409, 'Bu ogohlantirish allaqachon qabul qilingan.', { reason: 'alert_not_open' }, { code: 'alert_not_open' }) }, MANAGE, { initialTab: 'alerts' });
    await user.click(await screen.findByRole('button', { name: /^Qabul qilish: Oxirgi zaxira/ }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Qabul qilish' }));

    const dialog = screen.getByRole('dialog');
    expect(await within(dialog).findByText(/allaqachon qabul qilingan yoki hal bo'lgan/)).toBeInTheDocument();
  });

  it('gives a viewer the list and no buttons', async () => {
    setup({}, VIEW, { initialTab: 'alerts' });
    await screen.findByRole('list', { name: "Ogohlantirishlar ro'yxati" });
    expect(screen.queryByRole('button', { name: /^Qabul qilish/ })).toBeNull();
    expect(screen.queryByRole('button', { name: /^Hal qilish/ })).toBeNull();
    expect(screen.getByText("Siz ko'rishingiz mumkin, o'zgartirishga ruxsat yo'q.")).toBeInTheDocument();
  });

  it('asks for the chosen state and kind, and shows the history with how each ended', async () => {
    const history = [
      alert({ id: 4, kind: 'scheduler_stale', kind_label: "Scheduler to'xtagan", title: 'Scheduler 6 daqiqadan beri yurak urishini yozmayapti', state: 'resolved', resolved: { by: null, at: minutesAgo(30), note: null, resolution: 'auto' } }),
      alert({ id: 5, state: 'resolved', resolved: { by: { id: 2, name: 'Sardor Operator' }, at: minutesAgo(50), note: 'Zaxira qo\'lda olindi', resolution: 'manual' } }),
      alert({ id: 6, kind: 'fiscal_failures', title: 'Fiskal', state: 'resolved', resolved: { by: null, at: minutesAgo(70), note: "Qoida o'chirildi", resolution: 'rule_disabled' } }),
    ];
    const { user, calls } = setup({ 'GET /monitoring/alerts': (request) => (new URL(request.url).searchParams.get('state') === 'resolved' ? alertsAnswer(history) : alertsAnswer()) }, MANAGE, { initialTab: 'alerts' });
    await screen.findByRole('list', { name: "Ogohlantirishlar ro'yxati" });
    expect(calls.find((call) => call.path === '/monitoring/alerts')?.query.get('state')).toBe('active');

    await user.click(screen.getByRole('button', { name: "Hal bo'lgan: 7 kunda 4" }));
    expect(await screen.findByText(/Muammo o'z-o'zidan tugadi/)).toBeInTheDocument();
    expect(screen.getByText(/Hal qildi: Sardor Operator/)).toBeInTheDocument();
    expect(screen.getByText(/Qoida o'chirilgani uchun yopildi/)).toBeInTheDocument();
    // History has no action buttons
    expect(screen.queryByRole('button', { name: /^Hal qilish:/ })).toBeNull();

    await user.selectOptions(screen.getByLabelText('Qoida turi'), 'queue_backlog');
    await waitFor(() => expect(calls.filter((call) => call.path === '/monitoring/alerts').some((call) => call.query.get('kind') === 'queue_backlog')).toBe(true));
  });

  it('marks the alert a link pointed at and says when all is well', async () => {
    setup({}, MANAGE, { initialTab: 'alerts', focusAlert: 2 });
    const list = await screen.findByRole('list', { name: "Ogohlantirishlar ro'yxati" });
    expect(list.children[1]).toHaveAttribute('aria-current', 'true');
    expect(list.children[0]).not.toHaveAttribute('aria-current');
  });

  it('says "Hammasi joyida" when nothing is open', async () => {
    setup({ 'GET /monitoring/alerts': () => alertsAnswer([], { open: 0, acknowledged: 0, resolved_7d: 0 }) }, MANAGE, { initialTab: 'alerts' });
    expect(await screen.findByText('Hammasi joyida')).toBeInTheDocument();
    expect(screen.getByText("Hozir ochiq ogohlantirish yo'q.")).toBeInTheDocument();
  });
});

describe('Monitoring: Yiqilgan vazifalar', () => {
  it('lists the failed jobs with their queue and first error line', async () => {
    setup({}, MANAGE, { initialTab: 'jobs' });
    const list = await screen.findByRole('list', { name: "Yiqilgan vazifalar ro'yxati" });
    const first = within(list.children[0] as HTMLElement);
    expect(first.getByText('SendTelegramRequest')).toBeInTheDocument();
    expect(first.getByText('default')).toBeInTheDocument();
    expect(first.getByText(/cURL error 28/)).toBeInTheDocument();
    expect(screen.getByText('Jami: 2 ta')).toBeInTheDocument();
    expect(within(list.children[1] as HTMLElement).getByText('reports')).toBeInTheDocument();
  });

  it('filters by job name and queue', async () => {
    const { user, calls } = setup({}, MANAGE, { initialTab: 'jobs' });
    await screen.findByRole('list', { name: "Yiqilgan vazifalar ro'yxati" });
    await user.type(screen.getByLabelText('Vazifa nomi'), 'Fiscal');
    await user.type(screen.getByLabelText('Navbat'), 'reports');
    await user.click(screen.getByRole('button', { name: "Ko'rsatish" }));
    await waitFor(() => expect(calls.filter((call) => call.path === '/monitoring/failed-jobs').some((call) => call.query.get('q') === 'Fiscal' && call.query.get('queue') === 'reports')).toBe(true));
  });

  it('opens the detail with the redacted error and data', async () => {
    const { user } = setup({ [`GET /monitoring/failed-jobs/${JOB_UUID}`]: detail }, MANAGE, { initialTab: 'jobs' });
    await user.click(await screen.findByRole('button', { name: /^Ochish: SendTelegramRequest/ }));

    const dialog = await screen.findByRole('dialog', { name: 'Yiqilgan vazifa' });
    expect(await within(dialog).findByText(/#0 \{main\}/)).toBeInTheDocument();
    expect(within(dialog).getByText('App\\Jobs\\SendTelegramRequest')).toBeInTheDocument();
    expect(within(dialog).getByText('5 / 5')).toBeInTheDocument();
    expect(within(dialog).getByText(/"api_key": "\[redacted\]"/)).toBeInTheDocument();
    expect(within(dialog).getByText('Kalitlar, tokenlar va telefon raqamlari yashirilgan.')).toBeInTheDocument();
    expect(dialog.textContent).not.toMatch(/bot\d+:/);
  });

  it('retries a job and says so', async () => {
    const { user, callsTo } = setup({ [`GET /monitoring/failed-jobs/${JOB_UUID}`]: detail, [`POST /monitoring/failed-jobs/${JOB_UUID}/retry`]: () => ok({ uuid: JOB_UUID, job: 'SendTelegramRequest', queue: 'default' }) }, MANAGE, { initialTab: 'jobs' });
    await user.click(await screen.findByRole('button', { name: /^Ochish: SendTelegramRequest/ }));
    await user.click(await screen.findByRole('button', { name: 'Qayta urinish' }));

    expect(await screen.findByText('Vazifa navbatga qaytarildi.')).toBeInTheDocument();
    expect(callsTo('POST', `/monitoring/failed-jobs/${JOB_UUID}/retry`)).toHaveLength(1);
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('deletes only with a reason, and asks for the 2FA code when the server wants it', async () => {
    let attempts = 0;
    const { user, callsTo } = setup(
      {
        [`GET /monitoring/failed-jobs/${JOB_UUID}`]: detail,
        [`DELETE /monitoring/failed-jobs/${JOB_UUID}`]: () => (++attempts === 1 ? json({ success: false, message: 'Amalni qayta tasdiqlang.', code: 'reauth_required' }, 403) : ok({ uuid: JOB_UUID, job: 'SendTelegramRequest', queue: 'default' })),
        'POST /auth/confirm': () => ok({ confirmed_until: '2026-10-27T12:10:00Z' }),
      },
      MANAGE,
      { initialTab: 'jobs' },
    );
    await user.click(await screen.findByRole('button', { name: /^Ochish: SendTelegramRequest/ }));
    await user.click(await screen.findByRole('button', { name: "O'chirish" }));

    const reasonDialog = screen.getByRole('dialog', { name: "Vazifani o'chirasizmi?" });
    await user.click(within(reasonDialog).getByRole('button', { name: "O'chirish" }));
    expect(await within(reasonDialog).findByText('Sabab kamida 3 belgi bo\'lsin.')).toBeInTheDocument();
    expect(callsTo('DELETE', `/monitoring/failed-jobs/${JOB_UUID}`)).toHaveLength(0);

    await user.type(within(reasonDialog).getByLabelText('Sabab'), 'Telegram chat o\'chirilgan');
    await user.click(within(reasonDialog).getByRole('button', { name: "O'chirish" }));

    const step = await screen.findByRole('alertdialog', { name: 'Amalni tasdiqlang' });
    await user.type(within(step).getByLabelText('6 xonali kod'), '123456');
    await user.click(within(step).getByRole('button', { name: 'Tasdiqlash' }));

    expect(await screen.findByText("Vazifa o'chirildi.")).toBeInTheDocument();
    const calls = callsTo('DELETE', `/monitoring/failed-jobs/${JOB_UUID}`);
    expect(calls).toHaveLength(2);
    expect(calls[1]?.body).toEqual({ reason: "Telegram chat o'chirilgan" });
  });

  it('shows a viewer the detail without retry or delete', async () => {
    const { user } = setup({ [`GET /monitoring/failed-jobs/${JOB_UUID}`]: detail }, VIEW, { initialTab: 'jobs' });
    await user.click(await screen.findByRole('button', { name: /^Ochish: SendTelegramRequest/ }));
    const dialog = await screen.findByRole('dialog', { name: 'Yiqilgan vazifa' });
    await within(dialog).findByText(/#0 \{main\}/);
    expect(within(dialog).queryByRole('button', { name: 'Qayta urinish' })).toBeNull();
    expect(within(dialog).queryByRole('button', { name: "O'chirish" })).toBeNull();
  });

  it('says a job that was retried by someone else is gone', async () => {
    const { user } = setup({ [`GET /monitoring/failed-jobs/${JOB_UUID}`]: () => refusal(404, 'Yiqilgan vazifa topilmadi.', { reason: 'not_found' }, { code: 'not_found' }) }, MANAGE, { initialTab: 'jobs' });
    await user.click(await screen.findByRole('button', { name: /^Ochish: SendTelegramRequest/ }));
    expect(await screen.findByText(/qayta urinilgan yoki o'chirilgan bo'lishi mumkin/)).toBeInTheDocument();
  });

  it('says calmly that there are none', async () => {
    setup({ 'GET /monitoring/failed-jobs': () => jobsAnswer([], 0) }, MANAGE, { initialTab: 'jobs' });
    expect(await screen.findByText("Yiqilgan vazifa yo'q")).toBeInTheDocument();
  });

  it('loads more by cursor', async () => {
    const page = (cursor: string | null) =>
      cursor
        ? ok({ data: [job({ id: 1, uuid: '00000000-0000-4000-8000-000000000001', job: 'GenerateMenuImageThumbnails' })], next_cursor: null, prev_cursor: null, per_page: 25, total: 2 })
        : ok({ data: [job()], next_cursor: 'abc', prev_cursor: null, per_page: 25, total: 2 });
    const { user, calls } = setup({ 'GET /monitoring/failed-jobs': (request) => page(new URL(request.url).searchParams.get('cursor')) }, MANAGE, { initialTab: 'jobs' });
    await user.click(await screen.findByRole('button', { name: 'Yana yuklash' }));
    expect(await screen.findByText('GenerateMenuImageThumbnails')).toBeInTheDocument();
    expect(calls.filter((call) => call.path === '/monitoring/failed-jobs').at(-1)?.query.get('cursor')).toBe('abc');
  });
});

describe('Monitoring: Qoidalar', () => {
  it('shows each rule with its switches, thresholds and defaults', async () => {
    setup({}, MANAGE, { initialTab: 'rules' });
    const list = await screen.findByRole('list', { name: 'Ogohlantirish qoidalari' });
    const queue = within(list.children[0] as HTMLElement);
    expect(queue.getByText("Navbat to'planishi")).toBeInTheDocument();
    expect(queue.getByText('1 ta ogohlantirish ochiq')).toBeInTheDocument();
    expect(queue.getByRole('switch', { name: "Navbat to'planishi: yoqilgan" })).toBeChecked();
    expect(queue.getByLabelText('Navbatdagi vazifalar soni (ta)')).toHaveValue(500);
    expect(queue.getByLabelText('Eng eski vazifa kutish vaqti (daqiqa)')).toHaveValue(10);
    expect(queue.getByText('Standart: 500 ta')).toBeInTheDocument();
    expect(queue.queryByLabelText(/Oxirgi qancha vaqt ichida/)).toBeNull();

    const spike = within(list.children[1] as HTMLElement);
    expect(spike.getByLabelText('Oxirgi qancha vaqt ichida (daqiqa)')).toHaveValue(15);
    expect(spike.queryByLabelText(/Eng eski vazifa/)).toBeNull();
  });

  it('saves only what changed, with a reason', async () => {
    const saved = RULES.map((r) => (r.kind === 'queue_backlog' ? { ...r, threshold: 800, telegram: false, updated_at: minutesAgo(0), updated_by: { id: 1, name: 'Aziza Karimova' } } : r));
    const { user, callsTo } = setup({ 'PUT /monitoring/rules/queue_backlog': () => ok({ rules: saved, telegram_configured: true }) }, MANAGE, { initialTab: 'rules' });
    const list = await screen.findByRole('list', { name: 'Ogohlantirish qoidalari' });
    const queue = within(list.children[0] as HTMLElement);

    expect(queue.getByRole('button', { name: "Saqlash: Navbat to'planishi" })).toBeDisabled();
    await user.clear(queue.getByLabelText('Navbatdagi vazifalar soni (ta)'));
    await user.type(queue.getByLabelText('Navbatdagi vazifalar soni (ta)'), '800');
    await user.click(queue.getByRole('switch', { name: "Navbat to'planishi: Telegramga yuborish" }));
    await user.click(queue.getByRole('button', { name: "Saqlash: Navbat to'planishi" }));

    const dialog = screen.getByRole('dialog', { name: "Qoidani o'zgartirasizmi?" });
    await user.type(within(dialog).getByLabelText('Sabab'), "Ish soatida shovqin ko'p");
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText("«Navbat to'planishi» qoidasi saqlandi.")).toBeInTheDocument();
    expect(callsTo('PUT', '/monitoring/rules/queue_backlog')[0]?.body).toEqual({ threshold: 800, telegram: false, reason: "Ish soatida shovqin ko'p" });
    // The form shows what the server kept, and the change is recorded under it
    expect(within((await screen.findByRole('list', { name: 'Ogohlantirish qoidalari' })).children[0] as HTMLElement).getByText(/Oxirgi o'zgartirish: Aziza Karimova/)).toBeInTheDocument();
  });

  it('refuses a value outside the range before asking the server', async () => {
    const { user, callsTo } = setup({}, MANAGE, { initialTab: 'rules' });
    const list = await screen.findByRole('list', { name: 'Ogohlantirish qoidalari' });
    const queue = within(list.children[0] as HTMLElement);
    await user.clear(queue.getByLabelText('Eng eski vazifa kutish vaqti (daqiqa)'));
    await user.type(queue.getByLabelText('Eng eski vazifa kutish vaqti (daqiqa)'), '5000');

    expect(queue.getByText("Qiymat 1 dan 1440 gacha bo'lsin.")).toBeInTheDocument();
    expect(queue.getByRole('button', { name: "Saqlash: Navbat to'planishi" })).toBeDisabled();
    expect(callsTo('PUT', '/monitoring/rules/queue_backlog')).toHaveLength(0);
  });

  it('warns when no Telegram group is set', async () => {
    setup({ 'GET /monitoring/rules': () => ok({ rules: RULES, telegram_configured: false }) }, MANAGE, { initialTab: 'rules' });
    expect(await screen.findByText(/Telegram guruhi sozlanmagan/)).toBeInTheDocument();
  });

  it('gives a viewer the rules read-only', async () => {
    setup({}, VIEW, { initialTab: 'rules' });
    const list = await screen.findByRole('list', { name: 'Ogohlantirish qoidalari' });
    const queue = within(list.children[0] as HTMLElement);
    expect(queue.getByRole('switch', { name: "Navbat to'planishi: yoqilgan" })).toBeDisabled();
    expect(queue.getByLabelText('Navbatdagi vazifalar soni (ta)')).toBeDisabled();
    expect(queue.queryByRole('button', { name: /^Saqlash/ })).toBeNull();
    expect(queue.getByText("Siz ko'rishingiz mumkin, o'zgartirishga ruxsat yo'q.")).toBeInTheDocument();
  });
});

describe('Monitoring: real-time', () => {
  it('asks for the alerts again when one opens, and keeps working when Reverb is off', async () => {
    const echo = new FakeEcho();
    realtime(echo);
    let answer = alertsAnswer();
    const { callsTo } = setup({ 'GET /monitoring/alerts': () => answer }, MANAGE, { initialTab: 'alerts' });
    await screen.findByRole('list', { name: "Ogohlantirishlar ro'yxati" });
    await waitFor(() => expect(echo.channels).toHaveLength(1));
    expect(echo.channels[0]?.name).toBe('platform.ops');
    echo.connectAll();
    expect([...echo.channel('platform.ops').listeners.keys()].sort()).toEqual(['.ops.alert.acknowledged', '.ops.alert.opened', '.ops.alert.resolved']);

    const before = callsTo('GET', '/monitoring/alerts').length;
    answer = alertsAnswer([alert({ id: 9, title: 'Yangi: scheduler to\'xtadi', kind: 'scheduler_stale' }), ...ALERTS]);
    echo.channel('platform.ops').emit('ops.alert.opened', { id: 9, kind: 'scheduler_stale', severity: 'critical', state: 'open' });
    expect(await screen.findByText("Yangi: scheduler to'xtadi")).toBeInTheDocument();
    expect(callsTo('GET', '/monitoring/alerts').length).toBeGreaterThan(before);
    await flush();
  });

  it('shows the page without any socket', async () => {
    setup({}, VIEW);
    expect(await screen.findByText("E'tibor kerak", { selector: 'p' })).toBeInTheDocument();
  });
});

describe('Monitoring: refresh', () => {
  it('asks for the health again every 30 seconds', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    try {
      const { callsTo } = setup({}, VIEW);
      await screen.findByText("E'tibor kerak", { selector: 'p' });
      expect(callsTo('GET', '/monitoring/health')).toHaveLength(1);
      await vi.advanceTimersByTimeAsync(30_500);
      await waitFor(() => expect(callsTo('GET', '/monitoring/health').length).toBeGreaterThanOrEqual(2));
    } finally {
      vi.useRealTimers();
    }
  });
});
