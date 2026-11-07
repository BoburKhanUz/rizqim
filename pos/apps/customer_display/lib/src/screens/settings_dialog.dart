import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../lan/display_lan.dart';
import '../settings/display_settings.dart';

/// Ekran sozlamalari: nima ko'rsatilishi, ovoz, mavzu va shrift. Mijoz ekranida xodim yo'q, shuning uchun bu yerda
/// faqat ko'rinishga tegishli narsa bor; ekranni uzish panelda (Sozlamalar → Qurilmalar → O'chirish). P6: "Lokal
/// tarmoq" — markaz kassa holati va qo'lda manzil (beacon topmasa).
class DisplaySettingsDialog extends StatelessWidget {
  const DisplaySettingsDialog({super.key, required this.settings, this.lan});

  final DisplaySettingsController settings;

  /// P6 lokal ko'prik; `null` yoki bu ekranga sozlanmagan — bo'lim yo'q.
  final DisplayLan? lan;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Dialog(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 520),
        child: ValueListenableBuilder<DisplaySettings>(
          valueListenable: settings,
          builder: (BuildContext context, DisplaySettings s, _) =>
              SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Text(
                      'Ekran sozlamalari',
                      style: RzType.title.copyWith(color: c.fg),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Nima ko\'rsatiladi',
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    const SizedBox(height: 8),
                    SegmentedButton<DisplayMode>(
                      key: const ValueKey<String>('mode'),
                      segments: <ButtonSegment<DisplayMode>>[
                        for (final DisplayMode m in DisplayMode.values)
                          ButtonSegment<DisplayMode>(
                            value: m,
                            label: Text(m.label),
                          ),
                      ],
                      selected: <DisplayMode>{s.mode},
                      onSelectionChanged: (Set<DisplayMode> v) =>
                          settings.update(s.copyWith(mode: v.single)),
                    ),
                    const SizedBox(height: 6),
                    Text(switch (s.mode) {
                      DisplayMode.auto =>
                        "Chek bor paytda chek, qolgan paytda tayyor raqamlar.",
                      DisplayMode.cart => "Faqat kassaning ikkinchi ekrani: chek, to'lov, «Rahmat».",
                      DisplayMode.board =>
                        'Faqat «Tayyorlanmoqda / Tayyor» raqamlar (televizor).',
                    }, style: RzType.footnote.copyWith(color: c.fgMuted)),
                    const SizedBox(height: 16),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Tayyor raqam ovozi'),
                      value: !s.muted,
                      onChanged: (bool on) =>
                          settings.update(s.copyWith(muted: !on)),
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text("Qorong'i mavzu"),
                      value: s.dark,
                      onChanged: (bool on) =>
                          settings.update(s.copyWith(dark: on)),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Shrift',
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    const SizedBox(height: 8),
                    SegmentedButton<double>(
                      key: const ValueKey<String>('font'),
                      segments: <ButtonSegment<double>>[
                        for (final double f in DisplaySettings.fontChoices)
                          ButtonSegment<double>(
                            value: f,
                            label: Text('${(f * 100).round()}%'),
                          ),
                      ],
                      selected: <double>{s.fontScale},
                      onSelectionChanged: (Set<double> v) =>
                          settings.update(s.copyWith(fontScale: v.single)),
                    ),
                    if (lan != null) _LanSection(lan: lan!),
                    const SizedBox(height: 20),
                    RzButton(
                      label: 'Yopish',
                      size: RzButtonSize.lg,
                      fullWidth: true,
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                  ],
                ),
              ),
        ),
      ),
    );
  }
}

/// "Lokal tarmoq": holat, markaz kassa va qo'lda manzil (IP yoki kassadagi `rizqim-lan://` havolasi).
class _LanSection extends StatefulWidget {
  const _LanSection({required this.lan});

  final DisplayLan lan;

  @override
  State<_LanSection> createState() => _LanSectionState();
}

class _LanSectionState extends State<_LanSection> {
  late final TextEditingController _address = TextEditingController(
    text: widget.lan.link.manualEndpoint?.toString() ?? '',
  );
  String? _error;

  @override
  void dispose() {
    _address.dispose();
    super.dispose();
  }

  Future<void> _connect() async {
    final String? error = await widget.lan.setManual(_address.text);
    if (!mounted) return;
    setState(() => _error = error);
    if (error == null) unawaited(widget.lan.link.retryNow());
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.lan,
    builder: (BuildContext context, _) {
      final RzColors c = context.rz;
      final LanLink link = widget.lan.link;
      if (!link.available) return const SizedBox.shrink();
      final String hub = link.hubName ?? 'kassa';
      final String status = switch (link.mode) {
        LanMode.cloud => 'Internet bor: odatdagi ish.',
        LanMode.lan => "Internet yo'q: chek va raqamlar Wi-Fi orqali kassadan.",
        LanMode.alone => "Internet ham, markaz kassa ham yo'q.",
      };
      return Padding(
        padding: const EdgeInsets.only(top: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text('Lokal tarmoq', style: RzType.headline.copyWith(color: c.fg)),
            const SizedBox(height: 6),
            Text(status, style: RzType.body.copyWith(color: c.fg)),
            Text(
              link.connected
                  ? 'Markaz: $hub (${link.endpoint})'
                  : 'Markaz: $hub',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            if (link.lastError != null && !link.connected)
              Text(
                link.lastError!,
                style: RzType.footnote.copyWith(color: c.dangerSoftFg),
              ),
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: TextField(
                    key: const ValueKey<String>('lan-address'),
                    controller: _address,
                    keyboardType: TextInputType.url,
                    decoration: InputDecoration(
                      labelText: 'Markaz manzili (ixtiyoriy)',
                      hintText: '192.168.1.20 yoki rizqim-lan://…',
                      errorText: _error,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: FilledButton(
                    onPressed: () => unawaited(_connect()),
                    child: const Text('Ulanish'),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    },
  );
}
