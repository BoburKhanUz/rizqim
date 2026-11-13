import 'dart:async';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:pos_ui/customer_display.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/board_view.dart';
import '../controller/display_controller.dart';
import '../lan/display_lan.dart';
import '../settings/display_settings.dart';
import 'settings_dialog.dart';

/// Mijoz ekrani: kassa cheki, salomlashish yoki raqamlar taxtasi; burchakda aloqa belgisi. Sozlamalar xodimsiz: pastki
/// chap burchakni 2 soniya bosib turish (tasodifan teginish ochmaydi).
class DisplayScreen extends StatelessWidget {
  const DisplayScreen({super.key, required this.controller});

  final DisplayController controller;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListenableBuilder(
          listenable: controller,
          builder: (BuildContext context, _) {
            final DisplayView view = controller.view;
            return Stack(
              children: <Widget>[
                Positioned.fill(
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 250),
                    child: KeyedSubtree(
                      key: ValueKey<DisplayView>(view),
                      child: switch (view) {
                        DisplayView.loading => const _Loading(),
                        DisplayView.welcome => DisplayWelcome(
                          name: controller.storeName,
                        ),
                        DisplayView.cart => CartView(
                          cart: controller.cart,
                          storeName: controller.storeName,
                        ),
                        DisplayView.board => BoardView(controller: controller),
                        DisplayView.notEnabled => _NotEnabled(
                          controller: controller,
                        ),
                      },
                    ),
                  ),
                ),
                Positioned(
                  top: 12,
                  right: 12,
                  child: _LinkChip(link: controller.link),
                ),
                Positioned(
                  left: 0,
                  bottom: 0,
                  width: 96,
                  height: 96,
                  child: _SettingsCorner(
                    settings: controller.settings,
                    lan: controller.lan,
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator());
}

class _NotEnabled extends StatelessWidget {
  const _NotEnabled({required this.controller});

  final DisplayController controller;

  @override
  Widget build(BuildContext context) {
    final String? error = controller.error;
    final DisplayMode mode = controller.settings.value.mode;
    final String reason = switch (mode) {
      DisplayMode.cart => 'Filial formatida «Mijoz ekrani» moduli yoqilmagan.',
      DisplayMode.board =>
        'Filial formatida «Raqamni chaqirish» moduli yoqilmagan.',
      DisplayMode.auto => 'Filial formatida «Mijoz ekrani» ham, «Raqamni chaqirish» ham yoqilmagan.',
    };
    return EmptyState(
      icon: Icons.visibility_off_outlined,
      title: error != null
          ? 'Ekran sozlanmagan'
          : "Bu ekran hozircha o'chirilgan",
      message:
          error ??
          "$reason Egasi hamkor panelida Sozlamalar → Format sahifasidan yoqadi.",
    );
  }
}

/// Aloqa belgisi: faqat muammo bo'lganda ko'rinadi (jonli yoki ulanmoqda paytida mijozni chalg'itmaydi). P6: internet
/// yo'q, lekin chek va raqamlar Wi-Fi orqali kassadan — kichik "Lokal tarmoq".
class _LinkChip extends StatelessWidget {
  const _LinkChip({required this.link});

  final DisplayLink link;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (
      String key,
      String label,
      IconData icon,
      Color bg,
      Color fg,
    ) = switch (link) {
      DisplayLink.offline => (
        'link-offline',
        "Aloqa yo'q",
        Icons.wifi_off_rounded,
        c.danger,
        c.onDanger,
      ),
      DisplayLink.lan => (
        'link-lan',
        'Lokal tarmoq',
        Icons.lan_outlined,
        c.warningSoft,
        c.warningSoftFg,
      ),
      _ => ('', '', Icons.circle, Colors.transparent, Colors.transparent),
    };
    if (key.isEmpty) return const SizedBox.shrink();
    return Semantics(
      liveRegion: true,
      label: label,
      child: DecoratedBox(
        key: ValueKey<String>(key),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(icon, color: fg, size: 22),
              const SizedBox(width: 8),
              Text(label, style: RzType.headline.copyWith(color: fg)),
            ],
          ),
        ),
      ),
    );
  }
}

class _SettingsCorner extends StatelessWidget {
  const _SettingsCorner({required this.settings, this.lan});

  final DisplaySettingsController settings;
  final DisplayLan? lan;

  /// Tasodifiy teginish ochmasligi uchun: burchakni shuncha bosib turish kerak.
  static const Duration hold = Duration(seconds: 2);

  @override
  Widget build(BuildContext context) {
    return RawGestureDetector(
      key: const ValueKey<String>('settings-corner'),
      behavior: HitTestBehavior.translucent,
      gestures: <Type, GestureRecognizerFactory>{
        LongPressGestureRecognizer:
            GestureRecognizerFactoryWithHandlers<LongPressGestureRecognizer>(
              () => LongPressGestureRecognizer(duration: hold),
              (LongPressGestureRecognizer r) => r.onLongPress = () => unawaited(
                showDialog<void>(
                  context: context,
                  builder: (BuildContext context) =>
                      DisplaySettingsDialog(settings: settings, lan: lan),
                ),
              ),
            ),
      },
      child: const SizedBox.expand(),
    );
  }
}
