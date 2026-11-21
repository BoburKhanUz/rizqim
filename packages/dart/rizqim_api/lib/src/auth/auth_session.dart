import 'dart:async';

import 'api_exception.dart';
import 'auth_remote.dart';
import 'models.dart';
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
       _clock = clock ?? DateTime.now;

  final AuthRemote remote;
  final CredentialStorage _storage;
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
  StaffSession? get staff => _staff;

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
  bool get staffContinued =>
      holdExpiredStaff && (_staff?.isExpired(_clock()) ?? false);

  /// When the signed-in staff member entered the PIN on this device (`null`: nobody is signed in).
  DateTime? get staffSignedInAt => _staff == null ? null : _staffSignedInAt;

  /// How long after the PIN login a continued staff member keeps the wider set of actions (owner decision: 12 h).
  static const Duration staffContinuationWindow = Duration(hours: 12);

  /// M5 S1 review (owner answer 3): the staff member is continued ([staffContinued]) and the PIN login is more than
  /// [staffContinuationWindow] old: only the bill, the payment and the close of open orders (payment is never
  /// blocked), no new rounds and no voids.
  bool get staffFinishOnly {
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
  }

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
    await _persist();
    _emit(const AuthEvent(AuthEventKind.terminalPaired));
    return paired;
  }

  /// Terminalni ushbu qurilmadan olib tashlaydi (serverdagi qurilmani egasi panelda o'chiradi).
  Future<void> forgetTerminal() async {
    _terminal = null;
    _staff = null;
    _setAside = null;
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
    _staffSignedInAt = _clock();
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
    if (staffContinued) _setAside = staff;
    _staff = null;
    _emit(AuthEvent(AuthEventKind.staffSignedOut, reason: reason));
    if (terminal == null) return;
    try {
      await remote.endStaffSession(terminal.device.uuid);
    } on ApiException {
      // Tarmoq yo'q yoki sessiya allaqachon yopilgan: qurilmada chiqildi, shu yetarli
    }
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
      if (holdExpiredStaff || !staff.isExpired(_clock())) return staff.token;
      _staff = null;
      _emit(const AuthEvent(AuthEventKind.staffSignedOut, reason: 'expired'));
    }
    return _user?.accessToken;
  }

  String? get terminalToken => _terminal?.token;

  /// Xodim tokenini server rad etdi (401): sessiya tugagan. A continued staff member ([staffContinued]) is set aside
  /// for the case that the PIN login cannot reach the server ([resumeStaffContinuation]).
  void handleStaffRejected(String sentToken) {
    final StaffSession? staff = _staff;
    if (staff == null || staff.token != sentToken) return;
    if (staffContinued) _setAside = staff;
    _staff = null;
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
