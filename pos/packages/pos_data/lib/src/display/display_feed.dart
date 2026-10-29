import 'dart:async';
import 'dart:math';

import 'display_models.dart';
import 'display_repository.dart';

/// P6-14: kassaning ikkinchi ekraniga chekni yuboradi (docs/POS_SPEC.md 2.14a). Kassa ilovasi chek o'zgargan sari
/// `publish` ni chaqiradi; qolganini shu sinf hal qiladi:
///
/// * **Kutmaydi va yiqilmaydi:** chaqiruv darhol qaytadi; xato (aloqa yo'q, modul o'chiq) kassa ishiga tegmaydi.
/// * **Oxirgisi yutadi:** taomlar ketma-ket qo'shilsa [debounce] ichida bitta yuborish ketadi; yuborish davom etayotganda
///   yangi chek kelsa, tugagach faqat eng oxirgisi yuboriladi.
/// * **Tartib:** har yuborishga o'sib boruvchi `seq` va ilova ishga tushishiga xos `session` qo'yiladi (server kech kelgan
///   eskisini tashlaydi, qayta ochilgan kassani eskirgan deb o'ylamaydi).
/// * `payment`, `paid` va `idle` kutmasdan ketadi (mijoz summani va "Rahmat"ni kutib o'tirmasin).
/// * Format moduli o'chiq bo'lsa ([enabled] `false`) hech narsa yuborilmaydi.
///
/// ```dart
/// final feed = pos.displayFeed;
/// feed.enabled = bootstrap.modules.has(PosModule.customerDisplay);
/// feed.publish(PosDisplayCart(state: PosDisplayScreen.cart, lines: [...], total: total));
/// feed.publish(PosDisplayCart(state: PosDisplayScreen.payment, ..., payment: PosDisplayPayment(qr: ...)));
/// feed.publish(PosDisplayCart(state: PosDisplayScreen.paid, ..., total: total));
/// feed.idle(); // "Keyingi mijoz"
/// ```
class DisplayFeed {
  DisplayFeed(
    this._repository, {
    this.debounce = const Duration(milliseconds: 150),
    String? session,
  }) : session = session ?? _newSession();

  final PosDisplayRepository _repository;

  /// Taomlar ketma-ket qo'shilganda yuborishlarni birlashtirish oralig'i.
  final Duration debounce;

  /// Shu ilova ishga tushishining belgisi (server `seq` ni shu doirada solishtiradi).
  final String session;

  /// `false` bo'lsa [publish] hech narsa qilmaydi (format `customer_display` modulini yoqmagan).
  bool enabled = true;

  /// Oxirgi muvaffaqiyatsiz yuborish (kassa ko'rsatmaydi; diagnostika va testlar uchun).
  Object? lastError;

  /// Nechta yuborish jo'natildi (testlar).
  int sent = 0;

  int _seq = 0;
  PosDisplayCart? _pending;
  String? _lastBody;
  Timer? _timer;
  Future<void>? _inFlight;
  bool _disposed = false;

  /// Chekni ikkinchi ekranga yuboradi (kutmaydi).
  void publish(PosDisplayCart cart) {
    if (!enabled || _disposed) return;
    _pending = cart;
    if (cart.state == PosDisplayScreen.cart && debounce > Duration.zero) {
      _timer ??= Timer(debounce, _kick);
    } else {
      _kick();
    }
  }

  /// "Keyingi mijoz": ekran salomlashishga qaytadi.
  void idle() => publish(const PosDisplayCart.idle());

  /// Kutayotgan yuborish ketguncha kutadi (ilova yopilishidan oldin, testlarda).
  Future<void> flush() async {
    if (_timer != null) _kick();
    while (_inFlight != null) {
      await _inFlight;
    }
  }

  void _kick() {
    _timer?.cancel();
    _timer = null;
    if (_inFlight != null) return; // tugagach _drain yana qaraydi
    _inFlight = _drain().whenComplete(() => _inFlight = null);
  }

  Future<void> _drain() async {
    while (!_disposed) {
      final PosDisplayCart? next = _pending;
      if (next == null) return;
      _pending = null;
      final PosDisplayCart numbered = next.copyWith(
        seq: ++_seq,
        session: session,
      );
      // Bir xil chekni takror yubormaymiz (kassa har qayta chizishda chaqirishi mumkin)
      final String body = _bodyOf(numbered);
      if (body == _lastBody) continue;
      try {
        await _repository.publish(numbered);
        _lastBody = body;
        lastError = null;
        sent++;
      } on Object catch (e) {
        lastError = e;
        // Keyingi o'zgarishda qayta urinadi; xato bo'lgan yuborish eslab qolinmaydi
        _lastBody = null;
      }
    }
  }

  static String _bodyOf(PosDisplayCart cart) {
    final Map<String, Object?> json = cart.toJson()
      ..remove('seq')
      ..remove('session');
    return json.toString();
  }

  Future<void> dispose() async {
    _disposed = true;
    _timer?.cancel();
    _timer = null;
    _pending = null;
    final Future<void>? running = _inFlight;
    if (running != null) await running;
  }

  static String _newSession() {
    final Random r = Random.secure();
    return List<String>.generate(
      8,
      (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();
  }
}
