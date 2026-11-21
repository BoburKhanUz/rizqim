import 'dart:async';

import 'api_exception.dart';
import 'auth_remote.dart';
import 'models.dart';
import 'staff_resume.dart';
import 'token_storage.dart';

/// [AuthSession] holati o'zgarganda ilovaga xabar (ekranni almashtirish uchun).
enum AuthEventKind {
  /// Terminal ulandi.
  terminalPaired,

  /// Terminal tokeni serverda o'chirilgan (401): qayta ulash kerak.
  terminalRevoked,

  /// Terminal shu qurilmadan olib tashlandi ([AuthSession.forgetTerminal]).
  terminalForgotten,

  /// Xodim PIN bilan kirdi.
  staffSignedIn,

  /// Xodim sessiyasi tugadi (chiqdi, muddati o'tdi yoki server rad etdi): PIN klaviaturasiga qaytish.
  staffSignedOut,

  /// Shaxsiy ilovada kirildi.
  userSignedIn,

  /// Access/refresh juftligi yangilandi.
  tokensRefreshed,

  /// Shaxsiy ilova sessiyasi tugadi (refresh token o'lgan yoki chiqildi): qaytadan kirish kerak.
  userSignedOut,
}

class AuthEvent {
  const AuthEvent(this.kind, {this.reason});

  final AuthEventKind kind;

  /// Masalan `refresh_reused`, `expired`, `unauthorized`, `logout`.
  final String? reason;

  @override
  String toString() => 'AuthEvent($kind${reason != null ? ', $reason' : ''})';
}

/// Qurilmadagi barcha kirish holati va tokenlar aylanishi.
///
/// * **Terminal** (POS/KDS): ulash kodi bilan olingan uzoq muddatli token. Saqlanadi.
/// * **Xodim**: terminalga PIN bilan kirgan odamning 12 soatlik tokeni. Faqat xotirada.
/// * **Shaxsiy ilova** (kuryer, hamkor, mijoz): qisqa access token + refresh token. Saqlanadi.
///
/// **Refresh single-flight**: [refresh] bir vaqtda faqat bitta `POST /auth/refresh` yuboradi, qolgan chaqiruvchilar
/// o'sha natijani kutadi. Backend sarflangan refresh token yana kelsa uni o'g'irlangan deb hisoblab butun oilani
/// bekor qiladi (docs/AUTH.md, "Refresh token"), shuning uchun ikkita parallel so'rov foydalanuvchini tizimdan
/// chiqarib yuborgan bo'lardi.
class AuthSession {
  AuthSession({
    required this.remote,
    required SecureKeyValueStore store,
    DateTime Function()? clock,
    this.refreshSkew = const Duration(seconds: 30),
  }) : _storage = CredentialStorage(store),
       _store = store,
       _clock = clock ?? DateTime.now;

  final AuthRemote remote;
  final CredentialStorage _storage;
  final SecureKeyValueStore _store;
  final DateTime Function() _clock;

  /// Access token tugashiga shuncha qolganda so'rovdan oldin yangilanadi (0: faqat 401 dan keyin).
  final Duration refreshSkew;

  final StreamController<AuthEvent> _events =
      StreamController<AuthEvent>.broadcast();

  PairedTerminal? _terminal;
  StaffSession? _staff;

  /// A continued staff member the server's 401 (or the screen lock) sent to the PIN keypad: back in [_staff] when the
  /// PIN login cannot reach the server; gone when one succeeds or the terminal changes.
  StaffSession? _setAside;
  DateTime? _staffSignedInAt;
  UserSession? _user;
  Future<UserSession>? _refreshing;

  /// Necha marta `POST /auth/refresh` yuborilgan (testlar va diagnostika uchun).
  int refreshCalls = 0;

  Stream<AuthEvent> get events => _events.stream;

  PairedTerminal? get terminal => _terminal;

  /// The signed-in staff member. A resumed one ([staffResumed]) whose 12 h are over, or whose PIN login the device
  /// clock now puts in the future, is signed out here (reason `resume_expired`).
  StaffSession? get staff {
    _checkResume();
    return _staff;
  }

  /// M5 S1-e (kassa, internetsiz rejim): xodim tokenining muddati o'tganda sessiya qurilmada o'chirilmaydi, serverning
  /// javobi hal qiladi. The till has no PIN login without the server, so dropping the staff member on a clock would
  /// lock the till in the middle of an outage (owner rule: payment is never blocked). With this on, an expired staff
  /// token is still sent, and while no answer comes the staff member stays as "continued" ([staffContinued]). Off
  /// (default): unchanged.
  ///
  /// M5 S1 review (a flapping link): a continued staff member ends only with a PIN login that SUCCEEDS. A server that
  /// answers refuses the dead token (401 -> [handleStaffRejected]) and the PIN keypad is shown, but the staff member is
  /// only set aside: when the PIN login then fails for the network ([signInStaff]), or the app finds the link gone
  /// again ([resumeStaffContinuation]), they go on as before. Plain server contact (a sync round, a probe) ends
  /// nothing.
  bool holdExpiredStaff = false;

  /// The staff token has expired and the staff member is kept only because no PIN login has been possible since
  /// ([holdExpiredStaff]). The app limits what such a staff member may do.
  ///
  /// M5 S6-a: a staff member resumed after an app restart ([staffResumed]) is continued too.
  bool get staffContinued =>
      staffResumed ||
      (holdExpiredStaff && (_staff?.isExpired(_clock()) ?? false));

  /// When the signed-in staff member entered the PIN on this device (`null`: nobody is signed in).
  DateTime? get staffSignedInAt => _staff == null ? null : _staffSignedInAt;

  /// How long after the PIN login a continued staff member keeps the wider set of actions (owner decision: 12 h).
  static const Duration staffContinuationWindow = Duration(hours: 12);

  /// M5 S1 review (owner answer 3): the staff member is continued ([staffContinued]) and the PIN login is more than
  /// [staffContinuationWindow] old: only the bill, the payment and the close of open orders (payment is never
  /// blocked), no new rounds and no voids.
  ///
  /// M5 S6-a: a resumed staff member ([staffResumed]) is always finish-only (owner answer 3: "only closing open
  /// tables and taking payment").
  bool get staffFinishOnly {
    if (staffResumed) return true;
    if (!staffContinued) return false;
    final DateTime? at = _staffSignedInAt;
    return at == null || _clock().difference(at) > staffContinuationWindow;
  }

  UserSession? get user => _user;
  DateTime now() => _clock();

  /// Terminal ulangan (qurilma uuid'i va tokeni bor).
  bool get isPaired => _terminal != null;

  /// Saqlangan kirish ma'lumotlarini o'qiydi. Ilova ishga tushganda bir marta chaqiriladi.
  Future<void> restore() async {
    final StoredCredentials stored = await _storage.read();
    storageError = _storage.readError;
    _terminal = stored.terminal;
    _user = stored.user;
    _staff = null;
    _setAside = null;
    _resumed = false;
    _resumable = null;
    String? raw;
    try {
      raw = await _store.read(staffResumeKey);
    } on Object {
      raw = null;
    }
    if (raw != null) {
      _resumeOnStore = true;
      _resumable = StaffResumeRecord.tryParse(raw);
      // A value that is not a whole record of this version is never trusted
      if (_resumable == null) _dropResume();
    }
  }

  // --- M5 S6-a: going on after an app restart during an outage -------------------------------------------------

  /// The secure-storage key of [StaffResumeRecord].
  static const String staffResumeKey = 'rizqim.pos.staff_resume.v1';

  /// The till with the offline mode (pos_data `PosData` turns it on): every successful PIN login writes a
  /// [StaffResumeRecord] (id, name, permissions, PIN-login time; never the PIN or a token). Off (default): nothing
  /// is written and nothing can be resumed.
  bool rememberStaffForResume = false;

  StaffResumeRecord? _resumable;
  bool _resumed = false;

  /// A record may be on the store (read at [restore] or written since): only then is it deleted (a store that could
  /// not be read is never touched for nothing).
  bool _resumeOnStore = false;
  Future<void> _resumeWrites = Future<void>.value();

  /// M5 S6 (review 1 M2): a monotonic clock (default: a [Stopwatch] started with this session). A resume ends when
  /// this much real time has passed since it began as was left of the 12 h by the device clock then, whatever the
  /// device clock says later. Tests replace it.
  Duration Function() monotonic = _stopwatch();

  static Duration Function() _stopwatch() {
    final Stopwatch w = Stopwatch()..start();
    return () => w.elapsed;
  }

  /// The highest device time seen while somebody was resumed on this run (and the `notBefore` given at the resume):
  /// a clock behind it was set back.
  DateTime? _seenMax;

  /// [monotonic] reading at which the resume of the current record ends (`null`: not resumed on this run).
  Duration? _resumeDeadline;

  /// The kept record while it may still be used at the device's [now] (12 h after the PIN login at most, never a PIN
  /// login in the future, never by a clock behind what this run already saw, never past the [monotonic] end of an
  /// earlier resume), a terminal is paired and nobody is signed in. An unusable record is deleted here. Whether the
  /// server is out of reach and whether the server records the mark is the caller's to decide (pos_data
  /// `PosSession.resumeOffer`).
  StaffResumeRecord? get resumableStaff {
    final StaffResumeRecord? r = _resumable;
    if (r == null || !rememberStaffForResume) return null;
    final DateTime now = _clock();
    final DateTime? seen = _seenMax;
    if (!r.usableAt(now) ||
        (seen != null && now.isBefore(seen)) ||
        _pastResumeDeadline) {
      _dropResume();
      return null;
    }
    if (_terminal == null || _staff != null) return null;
    return r;
  }

  bool get _pastResumeDeadline {
    final Duration? deadline = _resumeDeadline;
    return deadline != null && monotonic() > deadline;
  }

  /// A staff member resumed from [StaffResumeRecord] on this run: no PIN login, no staff token. Continued and
  /// finish-only ([staffContinued], [staffFinishOnly]). Checks the end first (the 12 h, the clock).
  bool get staffResumed {
    _checkResume();
    return _resumed && _staff != null;
  }

  /// Signs the kept staff member in again without a PIN ([AuthEventKind.staffSignedIn], reason `resumed`), with only
  /// the stored permissions that are also in [allowedPermissions]. No token is held: nothing goes to the server as
  /// this staff member except through the journal. [notBefore]: the latest time the till already saw (its last
  /// server contact, its newest queued operation); a device clock behind it (or behind what this run saw) was set
  /// back and nothing is resumed (the record is deleted). `false`: nothing to resume ([resumableStaff]) or the clock.
  bool resumeStaff({
    required Set<String> allowedPermissions,
    DateTime? notBefore,
  }) {
    final StaffResumeRecord? r = resumableStaff;
    if (r == null) return false;
    final DateTime now = _clock();
    if (notBefore != null && now.isBefore(notBefore)) {
      _dropResume();
      return false;
    }
    _seenMax = now;
    // The rest of the 12 h by the device clock now, counted from here on by the monotonic clock (never longer than
    // an earlier resume of the same record allowed)
    final Duration end = monotonic() + r.until.difference(now);
    final Duration? earlier = _resumeDeadline;
    _resumeDeadline = earlier == null || end < earlier ? end : earlier;
    _setAside = null;
    _staff = StaffSession(
      token: '',
      expiresAt: r.until,
      userId: r.userId,
      userName: r.userName,
      role: '',
      permissions: r.permissions.intersection(allowedPermissions),
    );
    _staffSignedInAt = r.signedInAt;
    _resumed = true;
    _emit(const AuthEvent(AuthEventKind.staffSignedIn, reason: 'resumed'));
    return true;
  }

  /// The server answered: the resumed staff member ([staffResumed]) goes to the PIN keypad
  /// ([AuthEventKind.staffSignedOut], reason `resume_ended`), a PIN login is possible now. M5 S6 (review 2 MEDIUM): the
  /// record is KEPT (any answer counts, a 502 or a captive portal too): when the link is found gone again before a PIN
  /// login succeeded, "Davom etish" is offered again (pos_data `PosSession.resumeOffer`). Nothing happens otherwise.
  void endStaffResume() {
    if (!_resumed || _staff == null) return;
    _endResume('resume_ended', keepRecord: true);
  }

  /// M5 S6: the shift this till worked in was closed: nothing is kept for a restart (a resume would have no shift).
  void forgetResumableStaff() {
    if (_resumed && _staff != null) {
      _endResume('shift_closed', keepRecord: false);
    } else {
      _dropResume();
    }
  }

  void _endResume(String reason, {required bool keepRecord}) {
    _resumed = false;
    _staff = null;
    if (!keepRecord) _dropResume();
    _emit(AuthEvent(AuthEventKind.staffSignedOut, reason: reason));
  }

  /// M5 S6 (review 1 M2): on every look at the resumed staff member (pos_data reads it before each action and each
  /// queued operation): a device clock behind the highest time seen this run (or the `notBefore` of the resume) was
  /// set back and ends the resume at once (reason `resume_clock`, record deleted: nothing about it can be trusted);
  /// the 12 h by the device clock or by the [monotonic] clock from the resume end it by [_resumeWindowOver].
  void _checkResume() {
    if (!_resumed || _staff == null) return;
    final DateTime now = _clock();
    final DateTime? seen = _seenMax;
    if (seen != null && now.isBefore(seen)) {
      _endResume('resume_clock', keepRecord: false);
      return;
    }
    _seenMax = now;
    final StaffResumeRecord? r = _resumable;
    if (r != null && r.usableAt(now) && !_pastResumeDeadline) return;
    _resumeWindowOver();
  }

  /// M5 S6, owner question Q1 (open): THE ONE place that says what happens when the 12 h after the PIN login are over
  /// while a staff member is resumed after a restart ([StaffResumeRecord.window] by the device clock, or by the
  /// [monotonic] clock from the resume). The window itself is decided above ([_checkResume]); only the consequence
  /// is here.
  ///
  /// Today (hard end): the staff member is signed out (reason `resume_expired`) and the record deleted; nobody can take
  /// a payment on this till until the server answers a PIN login. A payment and the close that follow it are one
  /// action and are never cut in two (pos_data `PosOffline.pay` reads the staff member once).
  ///
  /// The owner's alternative ("payment and close still allowed past 12 h, marked staff_unverified") is a change here:
  /// keep `_resumed` and set a past-the-window flag instead of [_endResume]; pos_data `PosOffline` then lets only
  /// `bill.pay` / `order.close` through for that flag and adds the server's `staff_unverified` mark to their payload.
  void _resumeWindowOver() => _endResume('resume_expired', keepRecord: false);

  void _keepForResume(StaffSession session, DateTime at) {
    if (!rememberStaffForResume) return;
    final StaffResumeRecord r = StaffResumeRecord(
      userId: session.userId,
      userName: session.userName,
      permissions: session.permissions,
      signedInAt: at.toUtc(),
    );
    _resumable = r;
    _resumeDeadline = null;
    _resumeOnStore = true;
    _writeResume(r.encode());
  }

  void _dropResume() {
    _resumable = null;
    _resumeDeadline = null;
    if (!_resumeOnStore) return;
    _resumeOnStore = false;
    _writeResume(null);
  }

  /// Written one after another (a later change never loses to an earlier one); a storage error leaves the record as
  /// the store has it (the in-memory state is what this run uses).
  void _writeResume(String? value) {
    _resumeWrites = _resumeWrites.then((_) async {
      try {
        if (value == null) {
          await _store.delete(staffResumeKey);
        } else {
          await _store.write(staffResumeKey, value);
        }
      } on Object {
        // Kept or not kept: the next sign-in or sign-out writes again
      }
    });
  }

  /// The pending writes of the record are on the store (tests, an orderly shutdown).
  Future<void> get resumeWritten => _resumeWrites;

  // --- Terminal: ulash ---------------------------------------------------------------------------------------

  /// Kod bilan terminalni ulaydi va tokenni saqlaydi. [code] ni [AuthRemote.pair] normallashtiradi.
  /// [pendingOperations] — [AuthRemote.pair] ga qarang.
  Future<PairedTerminal> pairTerminal(
    String code, {
    String? name,
    String? platform,
    String? appVersion,
    String? app,
    int? pendingOperations,
  }) async {
    final PairedTerminal paired = await remote.pair(
      code,
      name: name,
      platform: platform,
      appVersion: appVersion,
      app: app,
      pendingOperations: pendingOperations,
    );
    _terminal = paired;
    _staff = null;
    _setAside = null;
    _resumed = false;
    _dropResume();
    await _persist();
    _emit(const AuthEvent(AuthEventKind.terminalPaired));
    return paired;
  }

  /// Terminalni ushbu qurilmadan olib tashlaydi (serverdagi qurilmani egasi panelda o'chiradi).
  Future<void> forgetTerminal() async {
    _terminal = null;
    _staff = null;
    _setAside = null;
    _resumed = false;
    _dropResume();
    await _persist();
    _emit(const AuthEvent(AuthEventKind.terminalForgotten));
  }

  /// "Terminalni uzish": terminal avval serverda o'zini bekor qiladi (`DELETE /terminal`: tokenlari va xodim
  /// sessiyalari to'xtaydi), so'ng qurilmadan unutiladi. Server javob bermasa (tarmoq yo'q) ham qurilmadan
  /// unutiladi va `false` qaytadi: unda serverdagi yozuvni panelda o'chirish kerak.
  Future<bool> unpairTerminal() async {
    if (_terminal == null) return true;
    var revoked = true;
    try {
      await remote.revokeTerminal();
    } on ApiException catch (e) {
      // 401: the server already revoked this terminal, the same outcome
      revoked = e.statusCode == 401;
    }
    if (_staff != null) {
      _staff = null;
      _setAside = null;
      _resumed = false;
      _dropResume();
      _emit(const AuthEvent(AuthEventKind.staffSignedOut, reason: 'unpair'));
    }
    await forgetTerminal();
    return revoked;
  }

  // --- Terminal: the key of the signed offline queue (M5 S3) --------------------------------------------------

  /// The sync key this terminal holds (`null`: none yet, or not a POS terminal). Kept in the secure storage in the
  /// same record as the terminal token; gone when the terminal goes.
  TerminalSyncKey? get syncKey => _terminal?.syncKey;

  Future<TerminalSyncKey?>? _renewingSyncKey;

  /// Asks the server for a key (`POST /terminal/sync-key`) and keeps it. The server ROTATES at every call and keeps
  /// only one older key, so the caller must not ask while operations signed with the current key are still queued
  /// (pos_data `PosSyncKeys` decides). Callers at the same time share one request. `null`: no terminal, or the
  /// terminal changed while the answer was on its way (the key is thrown away). Errors are thrown; the key the till
  /// has stays.
  Future<TerminalSyncKey?> renewSyncKey() => _renewingSyncKey ??=
      _renewSyncKeyOnce().whenComplete(() => _renewingSyncKey = null);

  Future<TerminalSyncKey?> _renewSyncKeyOnce() async {
    final PairedTerminal? asked = _terminal;
    if (asked == null) return null;
    final TerminalSyncKey key = await remote.issueSyncKey();
    if (!identical(_terminal, asked)) return null;
    _terminal = asked.withSyncKey(key);
    // The old key is gone on the server after the next rotation: the new one is written at once
    await _persist();
    return key;
  }

  // --- Terminal: xodim ---------------------------------------------------------------------------------------

  Future<List<StaffMember>> loadStaff() => remote.terminalStaff();

  /// PIN bilan kirish. Oldingi xodim sessiyasi serverda ham almashadi.
  ///
  /// M5 S1 review: the server could not be reached and a continued staff member was waiting at the keypad
  /// ([holdExpiredStaff]): they go on ([resumeStaffContinuation]); the error is thrown as before.
  Future<StaffSession> signInStaff({
    int? userId,
    String? staffCode,
    required String pin,
  }) async {
    final PairedTerminal? terminal = _terminal;
    if (terminal == null) throw StateError('Terminal ulanmagan.');
    final StaffSession session;
    try {
      session = await remote.startStaffSession(
        terminal.device.uuid,
        userId: userId,
        staffCode: staffCode,
        pin: pin,
      );
    } on ApiException catch (e) {
      if (e.isNetworkError) resumeStaffContinuation();
      rethrow;
    }
    _staff = session;
    _setAside = null;
    _resumed = false;
    _staffSignedInAt = _clock();
    // M5 S6-a: the last PIN login on this till replaces the kept one
    _keepForResume(session, _staffSignedInAt!);
    _emit(const AuthEvent(AuthEventKind.staffSignedIn));
    return session;
  }

  /// The PIN keypad is shown, the server cannot be reached and a continued staff member was set aside
  /// ([holdExpiredStaff]): they are signed in again, still continued ([AuthEventKind.staffSignedIn], reason
  /// `continued`). `false`: nobody to bring back (or somebody is signed in).
  bool resumeStaffContinuation() {
    final StaffSession? kept = _setAside;
    if (kept == null || _staff != null || _terminal == null) return false;
    _setAside = null;
    _staff = kept;
    _emit(const AuthEvent(AuthEventKind.staffSignedIn, reason: 'continued'));
    return true;
  }

  /// Ekran qulfi / smena oxiri. Avval qurilmada chiqiladi, keyin serverga aytiladi; tarmoq bo'lmasa ham xodim
  /// chiqib ketgan bo'ladi (token baribir 12 soatda o'ladi).
  Future<void> signOutStaff({String reason = 'logout'}) async {
    final PairedTerminal? terminal = _terminal;
    final StaffSession? staff = _staff;
    if (staff == null) return;
    // A continued staff member who locks the screen has no PIN login to come back with until the server answers
    // (M5 S6-a: one resumed after a restart is not: the kept record offers them again, see below)
    if (staffContinued && !_resumed) _setAside = staff;
    _resumed = false;
    _staff = null;
    _emit(AuthEvent(AuthEventKind.staffSignedOut, reason: reason));
    // M5 S6 (review 1 M1): the kept record goes only when the server hears the sign-out. While it is out of reach no
    // PIN login is possible: the record stays and the PIN keypad offers "Davom etish" again (a resumed staff member
    // who locks the screen, a PIN-signed one who locks during the outage, the idle lock)
    final StaffResumeRecord? kept = _resumable;
    if (terminal == null) return;
    bool heard;
    try {
      await remote.endStaffSession(terminal.device.uuid);
      heard = true;
    } on ApiException catch (e) {
      // Tarmoq yo'q yoki sessiya allaqachon yopilgan: qurilmada chiqildi, shu yetarli
      heard = e.isUnauthorized;
    }
    // (a PIN login meanwhile wrote its own record: that one stays)
    if (heard && identical(_resumable, kept) && _staff == null) _dropResume();
  }

  // --- Shaxsiy ilova: kirish va refresh -----------------------------------------------------------------------

  /// Kirish javobidan (`/auth/telegram`, `/auth/phone/verify` + `device`) olingan sessiyani saqlaydi.
  Future<void> signInUser(UserSession session) async {
    _user = session;
    await _persist();
    _emit(const AuthEvent(AuthEventKind.userSignedIn));
  }

  /// Chiqish: avval serverga (joriy sessiya va refresh tokenlar yopiladi), keyin qurilmadan. Server javob bermasa ham
  /// qurilmadan o'chiriladi.
  Future<void> signOutUser({String reason = 'logout'}) async {
    if (_user == null) return;
    try {
      await remote.logout();
    } on ApiException {
      // Tarmoq yo'q: baribir qurilmadan chiqiladi
    }
    await _dropUser(reason);
  }

  /// Access token yangilanadi. Bir vaqtda chaqirilganlar bitta so'rovni kutadi (single-flight).
  ///
  /// Refresh token o'lgan bo'lsa (`refresh_reused`, `refresh_invalid`, `refresh_expired`) sessiya o'chiriladi,
  /// [AuthEventKind.userSignedOut] yuboriladi va [ApiException] tashlanadi. Tarmoq xatosi, 5xx yoki 429 da
  /// tokenlar saqlanadi (keyinroq qayta uriniladi).
  Future<UserSession> refresh() =>
      _refreshing ??= _refreshOnce().whenComplete(() => _refreshing = null);

  Future<UserSession> _refreshOnce() async {
    final UserSession? current = _user;
    if (current == null) {
      throw const ApiException(
        message: 'Kirilmagan.',
        statusCode: 401,
        reason: 'no_session',
      );
    }
    refreshCalls++;
    final UserSession next;
    try {
      next = await remote.refresh(
        refreshToken: current.refreshToken,
        deviceUuid: current.deviceUuid,
      );
    } on ApiException catch (e) {
      if (e.isRefreshDead) await _dropUser(e.reason!);
      rethrow;
    }
    // Yangi juftlik darhol yoziladi: eski refresh token serverda sarflangan, uni yo'qotib bo'lmaydi
    _user = next;
    await _persist();
    _emit(const AuthEvent(AuthEventKind.tokensRefreshed));
    return next;
  }

  /// So'rovdan oldin: access token tugash arafasida bo'lsa yangilaydi. Xato bo'lsa jim (so'rov baribir yuboriladi,
  /// 401 bo'lsa [AuthInterceptor] qayta urinadi).
  Future<void> refreshIfExpiring() async {
    final UserSession? current = _user;
    if (current == null || refreshSkew == Duration.zero) return;
    if (current.accessExpiresAt.difference(_clock()) > refreshSkew) return;
    try {
      await refresh();
    } on ApiException {
      // 401 da interceptor qaytadan urinadi
    }
  }

  // --- Interceptor uchun ---------------------------------------------------------------------------------------

  /// Xodim tokeni (muddati o'tmagan bo'lsa) yoki shaxsiy ilova tokeni. [holdExpiredStaff] yoqilgan bo'lsa muddati
  /// o'tgan xodim tokeni ham yuboriladi (server 401 bersa sessiya yopiladi).
  String? get currentToken {
    final StaffSession? staff = _staff;
    if (staff != null) {
      // M5 S6-a: a resumed staff member holds no token: no staff token is sent on this run
      if (_resumed) return _user?.accessToken;
      if (holdExpiredStaff || !staff.isExpired(_clock())) return staff.token;
      _staff = null;
      _dropResume();
      _emit(const AuthEvent(AuthEventKind.staffSignedOut, reason: 'expired'));
    }
    return _user?.accessToken;
  }

  String? get terminalToken => _terminal?.token;

  /// Xodim tokenini server rad etdi (401): sessiya tugagan. A continued staff member ([staffContinued]) is set aside
  /// for the case that the PIN login cannot reach the server ([resumeStaffContinuation]).
  void handleStaffRejected(String sentToken) {
    final StaffSession? staff = _staff;
    if (staff == null || staff.token != sentToken || _resumed) return;
    if (staffContinued) _setAside = staff;
    _staff = null;
    // M5 S6-a: the server refused the staff member: nothing is kept for a restart
    _dropResume();
    _emit(
      AuthEvent(
        AuthEventKind.staffSignedOut,
        // A held token ([holdExpiredStaff]) that the server refuses is the plain expiry
        reason: staff.isExpired(_clock()) ? 'expired' : 'unauthorized',
      ),
    );
  }

  /// Terminal tokenini server rad etdi (401): terminal o'chirilgan.
  Future<void> handleTerminalRejected(String sentToken) async {
    if (_terminal?.token != sentToken) return;
    _terminal = null;
    _staff = null;
    _setAside = null;
    _resumed = false;
    _dropResume();
    await _persist();
    _emit(const AuthEvent(AuthEventKind.terminalRevoked));
  }

  Future<void> _dropUser(String reason) async {
    _user = null;
    await _persist();
    _emit(AuthEvent(AuthEventKind.userSignedOut, reason: reason));
  }

  /// Oxirgi o'qish yoki yozish muvaffaqiyatsiz bo'lsa (masalan, Linux'da kalitlar xizmati yo'q) shu xato; aks holda
  /// `null`. Yozuv xatosida kirish qurilma xotirasida ishlayveradi, lekin qayta ishga tushirilganda tokenlar yo'qoladi;
  /// o'qish xatosida saqlangan token o'qilmadi (omborda qolgan bo'lishi mumkin) va ilova qayta ulashni so'raydi:
  /// ilova buni foydalanuvchiga ko'rsatishi kerak.
  Object? storageError;

  Future<void> _persist() async {
    try {
      await _storage.write(StoredCredentials(terminal: _terminal, user: _user));
      storageError =
          _storage.readError; // a skipped delete keeps the earlier read error
    } on Object catch (e) {
      storageError = e;
    }
  }

  void _emit(AuthEvent event) {
    if (!_events.isClosed) _events.add(event);
  }

  Future<void> dispose() => _events.close();
}
