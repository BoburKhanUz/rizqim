import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_ui/customer_display.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Ikkinchi ekran dvigatelining ilovasi (`main.dart` dagi `customerScreenMain`): kassa yuborgan chekni mijoz ekrani
/// ilovasi bilan bir xil ko'rinishda chizadi ([CartView], [PaidView], [DisplayWelcome]). Plagin yo'q, server yo'q:
/// hammasi [CustomerScreenReceiver] orqali keladi.
class CustomerScreenApp extends StatefulWidget {
  const CustomerScreenApp({
    super.key,
    required this.receiver,
    this.paidHold = const Duration(seconds: 8),
  });

  final CustomerScreenReceiver receiver;

  /// "Rahmat!" qancha turadi (kassa "Keyingi mijoz" ni bossa — darhol salomlashish).
  final Duration paidHold;

  @override
  State<CustomerScreenApp> createState() => _CustomerScreenAppState();
}

class _CustomerScreenAppState extends State<CustomerScreenApp> {
  StreamSubscription<Map<String, Object?>>? _sub;
  String? _storeName;
  PosDisplayCart? _cart;
  Timer? _paidTimer;

  @override
  void initState() {
    super.initState();
    _sub = widget.receiver.messages.listen(_onMessage);
    unawaited(widget.receiver.start());
  }

  @override
  void dispose() {
    _paidTimer?.cancel();
    unawaited(_sub?.cancel());
    super.dispose();
  }

  void _onMessage(Map<String, Object?> m) {
    switch (m['type']) {
      case 'store':
        final Object? name = m['name'];
        setState(
          () => _storeName = name is String && name.trim().isNotEmpty
              ? name.trim()
              : null,
        );
      case 'cart':
        final Object? raw = m['cart'];
        if (raw is! Map) return;
        final PosDisplayCart cart;
        try {
          cart = PosDisplayCart.fromJson(PosJson.of(raw));
        } on Object {
          return;
        }
        _paidTimer?.cancel();
        if (cart.state == PosDisplayScreen.paid) {
          _paidTimer = Timer(widget.paidHold, () {
            if (mounted) setState(() => _cart = null);
          });
        }
        setState(() => _cart = cart);
    }
  }

  @override
  Widget build(BuildContext context) {
    final PosDisplayCart? cart = _cart;
    final bool welcome = cart == null || cart.isIdle;
    return MaterialApp(
      title: 'Mijoz ekrani',
      debugShowCheckedModeBanner: false,
      theme: RzTheme.light(),
      darkTheme: RzTheme.dark(),
      themeMode: ThemeMode.dark,
      // Small customer screens (7" D2 mini, 1024x600): the display type sizes are made for 10"+ screens
      builder: (BuildContext context, Widget? child) {
        final MediaQueryData mq = MediaQuery.of(context);
        return MediaQuery(
          data: mq.copyWith(
            textScaler: TextScaler.linear(mq.size.height < 700 ? 0.8 : 1),
          ),
          child: child ?? const SizedBox.shrink(),
        );
      },
      home: Scaffold(
        body: SafeArea(
          child: AnimatedSwitcher(
            duration: const Duration(milliseconds: 250),
            child: welcome
                ? DisplayWelcome(
                    key: const ValueKey<String>('welcome'),
                    name: _storeName,
                  )
                : CartView(
                    key: const ValueKey<String>('cart'),
                    cart: cart,
                    storeName: _storeName,
                  ),
          ),
        ),
      ),
    );
  }
}
