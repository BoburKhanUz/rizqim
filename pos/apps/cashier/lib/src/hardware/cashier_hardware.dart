import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';

/// Kassa qurilmasining uskunasi (P6 uskunalar): printerni qidirish, shtrix-kod skanerlari va ikkinchi ekran.
///
/// * [discovery] — sozlamalardagi "Printerni qidirish" (LAN 9100, Bluetooth, USB, Sunmi).
/// * [scans] — klaviatura sifatida ulangan skaner (`pos_ui` `KeyboardScanListener`, ilova ildizida) va qurilmaning
///   ichki skaneri (Sunmi broadcast) bitta oqimda.
/// * [customerScreen] — Sunmi D2 turidagi ikkinchi ekran: kassa chekini serversiz ko'rsatadi
///   ([startCustomerScreen]).
///
/// Testlarda soxta kanallar bilan beriladi; standart holatda Android'dagina plaginga murojaat qilinadi.
class CashierHardware {
  CashierHardware({
    PrinterDiscovery? discovery,
    CustomerScreen? customerScreen,
    Stream<BarcodeScan>? deviceScans,
    bool? android,
  }) : _android = android ?? (!kIsWeb && Platform.isAndroid),
       discovery = discovery ?? PrinterDiscovery(),
       customerScreen = customerScreen ?? CustomerScreen() {
    final Stream<BarcodeScan>? device =
        deviceScans ?? (_android ? BroadcastScanner().scans : null);
    if (device != null) {
      _deviceSub = device.listen(scans.add, onError: (Object _) {});
    }
  }

  final bool _android;
  final PrinterDiscovery discovery;
  final CustomerScreen customerScreen;
  final CashierScans scans = CashierScans();
  StreamSubscription<BarcodeScan>? _deviceSub;
  CustomerScreenBridge? _bridge;

  /// Bu qurilma Android'mi (Bluetooth, USB OTG, Sunmi, ikkinchi ekran shu yerda).
  bool get android => _android;

  /// Ikkinchi ekranni ochadi (bo'lsa) va chekni [feed] dan uzatadi. Takror chaqirish do'kon nomini yangilaydi.
  Future<void> startCustomerScreen(DisplayFeed feed, {String? storeName}) {
    final CustomerScreenBridge bridge = _bridge ??= CustomerScreenBridge(
      screen: customerScreen,
      feed: feed,
    );
    return bridge.start(storeName: storeName);
  }

  Future<void> dispose() async {
    await _deviceSub?.cancel();
    await _bridge?.stop();
    scans.dispose();
  }
}

/// O'qilgan kodlar: oxirgisi ([last], sozlamalardagi "Skaner" kartasi) va oqim ([stream]).
///
/// Katalogda hozircha shtrix-kod yo'q (POS katalogi `sku`/`barcode` bermaydi), shuning uchun kod taomga
/// bog'lanmaydi: ekranlar [stream] ga obuna bo'lib o'z ishini qiladi (masalan, keyin: taomni kod bo'yicha qo'shish,
/// sovg'a kartasi, buyurtma QR'i).
class CashierScans extends ChangeNotifier {
  final StreamController<BarcodeScan> _controller =
      StreamController<BarcodeScan>.broadcast();
  BarcodeScan? _last;
  bool _disposed = false;

  BarcodeScan? get last => _last;

  Stream<BarcodeScan> get stream => _controller.stream;

  void add(BarcodeScan scan) {
    if (_disposed) return;
    _last = scan;
    _controller.add(scan);
    notifyListeners();
  }

  /// Klaviatura skaneri (`KeyboardScanListener.onScan`).
  void addKeyboard(String code) =>
      add(BarcodeScan(code, source: ScanSource.keyboard));

  @override
  void dispose() {
    if (_disposed) return;
    _disposed = true;
    unawaited(_controller.close());
    super.dispose();
  }
}

/// Kassa chekini ([DisplayFeed.publish]) qurilmaning ikkinchi ekraniga uzatadi: `store` (do'kon nomi) va `cart`
/// (`PosDisplayCart.toJson()`) xabarlari. Ekran yo'q bo'lsa hech narsa qilmaydi.
class CustomerScreenBridge {
  CustomerScreenBridge({required this.screen, required this.feed});

  final CustomerScreen screen;
  final DisplayFeed feed;
  bool _listening = false;

  Future<void> start({String? storeName}) async {
    if (!screen.isShown && !await screen.show()) return;
    await screen.send(<String, Object?>{'type': 'store', 'name': storeName});
    if (!_listening) {
      _listening = true;
      feed.addLocalListener(_onCart);
    }
  }

  void _onCart(PosDisplayCart cart) => unawaited(
    screen.send(<String, Object?>{'type': 'cart', 'cart': cart.toJson()}),
  );

  Future<void> stop() async {
    if (_listening) feed.removeLocalListener(_onCart);
    _listening = false;
    await screen.hide();
  }
}
