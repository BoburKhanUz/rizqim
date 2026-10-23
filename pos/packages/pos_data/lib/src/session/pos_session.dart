import 'dart:async';

import 'package:rizqim_api/rizqim_api.dart';

import '../bootstrap/bootstrap_models.dart';
import '../bootstrap/bootstrap_repository.dart';
import 'approvals.dart';

/// Kassa yoki ofitsiant terminalining holati.
enum PosSessionPhase {
  /// Terminal ulanmagan: ulash ekrani.
  unpaired,

  /// Ulangan, hech kim kirmagan: PIN ekrani (ekran qulfi).
  locked,

  /// Xodim kirdi, bootstrap hali yuklanmagan.
  signedIn,

  /// Xodim kirdi va bootstrap yuklandi: ish ekrani.
  ready,
}

/// Ulangan terminal + xodim PIN sessiyasi ustidagi holat ([AuthSession] ni o'raydi): kim kirgan, uning ruxsatlari,
/// filial, bootstrap (sozlamalar, smena) va menejer tasdig'i ([approvals]).
///
/// Xodim chiqqanda (qo'lda, harakatsizlik, server rad etdi) yoki terminal uzilganda bootstrap tozalanadi. Har
/// o'zgarish [changes] ga chiqadi (UI shu bilan qayta chiziladi).
class PosSession {
  PosSession({
    required this.auth,
    required this.bootstrapRepository,
    required this.approvals,
  }) {
    _authSub = auth.events.listen(_onAuth);
  }

  final AuthSession auth;
  final PosBootstrapRepository bootstrapRepository;

  /// Menejer tasdig'i (`POST /pos/approvals`).
  final ApprovalService approvals;

  final StreamController<PosSessionPhase> _changes =
      StreamController<PosSessionPhase>.broadcast();
  late final StreamSubscription<AuthEvent> _authSub;
  PosShiftSummary? _shift;
  bool _shiftOverridden = false;

  /// Holat o'zgardi (kirish, chiqish, ulash, bootstrap, smena).
  Stream<PosSessionPhase> get changes => _changes.stream;

  PosSessionPhase get phase {
    if (auth.terminal == null) return PosSessionPhase.unpaired;
    if (auth.staff == null) return PosSessionPhase.locked;
    return bootstrap == null ? PosSessionPhase.signedIn : PosSessionPhase.ready;
  }

  PairedTerminal? get terminal => auth.terminal;
  StaffSession? get staff => auth.staff;
  bool get isPaired => auth.terminal != null;
  bool get isSignedIn => auth.staff != null;

  /// Oxirgi yuklangan bootstrap (xodim kirmagan yoki hali yuklanmagan — `null`).
  PosBootstrap? get bootstrap =>
      auth.staff == null ? null : bootstrapRepository.current;

  /// Filial: bootstrap'dagi, bo'lmasa terminal ulangan filial.
  int? get branchId =>
      bootstrap?.branch.id ?? terminal?.branch?.id ?? terminal?.device.branchId;

  String? get branchName => bootstrap?.branch.name ?? terminal?.branch?.name;
  String? get storeName => bootstrap?.store.name ?? terminal?.store?.name;

  /// Kirgan xodim.
  int? get staffId => staff?.userId;
  String? get staffName => staff?.userName;

  /// `owner`, `manager`, `cashier`, `waiter`, `cook`.
  String? get staffRole => staff?.role;

  Set<String> get permissions => staff?.permissions ?? const <String>{};

  bool can(String permission) => staff?.can(permission) ?? false;

  /// Xodimda ruxsat yo'q, lekin menejer tasdig'i bilan bajarsa bo'ladi.
  bool needsApproval(String permission) =>
      !can(permission) && PosPermissions.approvable.contains(permission);

  /// Terminalni uzish faqat menejer va egaga (`devices.manage`).
  bool get canUnpair => can(PosPermissions.devicesManage);

  /// Shu terminalda ochiq smena (bootstrap'dan yoki [updateShift] dan).
  PosShiftSummary? get shift => _shiftOverridden ? _shift : bootstrap?.shift;

  /// Ekran qulfi muddati: filial sozlamasi (`screen_lock_minutes`; 0 — qulflanmaydi). Bootstrap yo'q — [fallback].
  Duration? idleTimeout({Duration? fallback = const Duration(minutes: 5)}) {
    final PosBootstrap? b = bootstrap;
    return b == null ? fallback : b.branch.settings.screenLock;
  }

  /// `GET /pos/bootstrap` va [changes].
  Future<PosBootstrap> loadBootstrap() async {
    final PosBootstrap b = await bootstrapRepository.load();
    _shiftOverridden = false;
    _shift = null;
    _emit();
    return b;
  }

  /// Smena ochildi, yopildi yoki `shift.changed` dan keyin qayta so'raldi.
  void updateShift(PosShiftSummary? shift) {
    _shift = shift;
    _shiftOverridden = true;
    _emit();
  }

  /// Ekranni qulflash (xodim chiqadi, terminal ulangan qoladi).
  Future<void> lock({String reason = 'logout'}) =>
      auth.signOutStaff(reason: reason);

  /// "Terminalni uzish" ([AuthSession.unpairTerminal]): `false` — server javob bermadi, qurilma baribir uzildi.
  Future<bool> unpair() => auth.unpairTerminal();

  void _onAuth(AuthEvent e) {
    switch (e.kind) {
      case AuthEventKind.staffSignedOut ||
          AuthEventKind.terminalRevoked ||
          AuthEventKind.terminalForgotten ||
          AuthEventKind.staffSignedIn:
        bootstrapRepository.clear();
        _shift = null;
        _shiftOverridden = false;
        _emit();
      case AuthEventKind.terminalPaired:
        _emit();
      default:
        return;
    }
  }

  void _emit() {
    if (!_changes.isClosed) _changes.add(phase);
  }

  Future<void> dispose() async {
    await _authSub.cancel();
    await _changes.close();
  }
}
