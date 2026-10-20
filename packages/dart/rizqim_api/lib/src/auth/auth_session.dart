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
  UserSession? _user;
  Future<UserSession>? _refreshing;

  /// Necha marta `POST /auth/refresh` yuborilgan (testlar va diagnostika uchun).
  int refreshCalls = 0;

  Stream<AuthEvent> get events => _events.stream;

  PairedTerminal? get terminal => _terminal;
  StaffSession? get staff => _staff;
  UserSession? get user => _user;
  DateTime now() => _clock();

  /// Terminal ulangan (qurilma uuid'i va tokeni bor).
  bool get isPaired => _terminal != null;

  /// Saqlangan kirish ma'lumotlarini o'qiydi. Ilova ishga tushganda bir marta chaqiriladi.
  Future<void> restore() async {
    final StoredCredentials stored = await _storage.read();
    _terminal = stored.terminal;
    _user = stored.user;
    _staff = null;
  }

  // --- Terminal: ulash ---------------------------------------------------------------------------------------

  /// Kod bilan terminalni ulaydi va tokenni saqlaydi. [code] ni [AuthRemote.pair] normallashtiradi.
  Future<PairedTerminal> pairTerminal(
    String code, {
    String? name,
    String? platform,
    String? appVersion,
  }) async {
    final PairedTerminal paired = await remote.pair(
      code,
      name: name,
      platform: platform,
      appVersion: appVersion,
    );
    _terminal = paired;
    _staff = null;
    await _persist();
    _emit(const AuthEvent(AuthEventKind.terminalPaired));
    return paired;
  }

  /// Terminalni ushbu qurilmadan olib tashlaydi (serverdagi qurilmani egasi panelda o'chiradi).
  Future<void> forgetTerminal() async {
    _terminal = null;
    _staff = null;
    await _persist();
    _emit(const AuthEvent(AuthEventKind.terminalForgotten));
  }

  // --- Terminal: xodim ---------------------------------------------------------------------------------------

  Future<List<StaffMember>> loadStaff() => remote.terminalStaff();

  /// PIN bilan kirish. Oldingi xodim sessiyasi serverda ham almashadi.
  Future<StaffSession> signInStaff({
    int? userId,
    String? staffCode,
    required String pin,
  }) async {
    final PairedTerminal? terminal = _terminal;
    if (terminal == null) throw StateError('Terminal ulanmagan.');
    final StaffSession session = await remote.startStaffSession(
      terminal.device.uuid,
      userId: userId,
      staffCode: staffCode,
      pin: pin,
    );
    _staff = session;
    _emit(const AuthEvent(AuthEventKind.staffSignedIn));
    return session;
  }

  /// Ekran qulfi / smena oxiri. Avval qurilmada chiqiladi, keyin serverga aytiladi; tarmoq bo'lmasa ham xodim
  /// chiqib ketgan bo'ladi (token baribir 12 soatda o'ladi).
  Future<void> signOutStaff({String reason = 'logout'}) async {
    final PairedTerminal? terminal = _terminal;
    if (_staff == null) return;
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

  /// Xodim tokeni (muddati o'tmagan bo'lsa) yoki shaxsiy ilova tokeni.
  String? get currentToken {
    final StaffSession? staff = _staff;
    if (staff != null) {
      if (!staff.isExpired(_clock())) return staff.token;
      _staff = null;
      _emit(const AuthEvent(AuthEventKind.staffSignedOut, reason: 'expired'));
    }
    return _user?.accessToken;
  }

  String? get terminalToken => _terminal?.token;

  /// Xodim tokenini server rad etdi (401): sessiya tugagan.
  void handleStaffRejected(String sentToken) {
    if (_staff?.token != sentToken) return;
    _staff = null;
    _emit(
      const AuthEvent(AuthEventKind.staffSignedOut, reason: 'unauthorized'),
    );
  }

  /// Terminal tokenini server rad etdi (401): terminal o'chirilgan.
  Future<void> handleTerminalRejected(String sentToken) async {
    if (_terminal?.token != sentToken) return;
    _terminal = null;
    _staff = null;
    await _persist();
    _emit(const AuthEvent(AuthEventKind.terminalRevoked));
  }

  Future<void> _dropUser(String reason) async {
    _user = null;
    await _persist();
    _emit(AuthEvent(AuthEventKind.userSignedOut, reason: reason));
  }

  /// Oxirgi yozuv muvaffaqiyatsiz bo'lsa (masalan, Linux'da kalitlar xizmati yo'q) shu xato; aks holda `null`.
  /// Kirish qurilma xotirasida ishlayveradi, lekin qayta ishga tushirilganda tokenlar yo'qoladi: ilova buni
  /// foydalanuvchiga ko'rsatishi kerak.
  Object? storageError;

  Future<void> _persist() async {
    try {
      await _storage.write(StoredCredentials(terminal: _terminal, user: _user));
      storageError = null;
    } on Object catch (e) {
      storageError = e;
    }
  }

  void _emit(AuthEvent event) {
    if (!_events.isClosed) _events.add(event);
  }

  Future<void> dispose() => _events.close();
}
