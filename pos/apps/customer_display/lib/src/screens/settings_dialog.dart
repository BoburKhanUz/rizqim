import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../settings/display_settings.dart';

/// Ekran sozlamalari: nima ko'rsatilishi, ovoz, mavzu va shrift. Mijoz ekranida xodim yo'q, shuning uchun bu yerda
/// faqat ko'rinishga tegishli narsa bor; ekranni uzish panelda (Sozlamalar → Qurilmalar → O'chirish).
class DisplaySettingsDialog extends StatelessWidget {
  const DisplaySettingsDialog({super.key, required this.settings});

  final DisplaySettingsController settings;

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
