import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../settings/kds_settings.dart';

/// Ekran sozlamalari: ovoz, mavzu, shrift, kutish chegaralari, avtomatik chiqish. O'zgarish darhol qo'llanadi va
/// qurilmada saqlanadi.
Future<void> showKdsSettings(
  BuildContext context,
  KdsSettingsController settings,
) => showDialog<void>(
  context: context,
  builder: (BuildContext context) => _SettingsDialog(settings: settings),
);

class _SettingsDialog extends StatelessWidget {
  const _SettingsDialog({required this.settings});

  final KdsSettingsController settings;

  static String _idleLabel(Duration? d) =>
      d == null ? "O'chiq" : '${d.inMinutes} daqiqa';

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Dialog(
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 560),
        child: ValueListenableBuilder<KdsSettings>(
          valueListenable: settings,
          builder: (BuildContext context, KdsSettings s, _) {
            Widget row(String title, String? hint, Widget control) =>
                ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 64),
                  child: Row(
                    children: <Widget>[
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            Text(
                              title,
                              style: RzType.headline.copyWith(color: c.fg),
                            ),
                            if (hint != null)
                              Text(
                                hint,
                                style: RzType.footnote.copyWith(
                                  color: c.fgMuted,
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      control,
                    ],
                  ),
                );

            DropdownButton<T> dropdown<T>(
              T value,
              List<T> choices,
              String Function(T) label,
              ValueChanged<T> onChanged,
            ) => DropdownButton<T>(
              value: value,
              style: RzType.body.copyWith(color: c.fg),
              itemHeight: 56,
              items: <DropdownMenuItem<T>>[
                for (final T v in choices)
                  DropdownMenuItem<T>(value: v, child: Text(label(v))),
              ],
              onChanged: (T? v) {
                if (v != null || null is T) onChanged(v as T);
              },
            );

            return SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text('Sozlamalar', style: RzType.title.copyWith(color: c.fg)),
                  const SizedBox(height: 8),
                  row(
                    'Yangi buyurtma ovozi',
                    null,
                    Switch(
                      value: !s.muted,
                      onChanged: (bool on) =>
                          settings.update(s.copyWith(muted: !on)),
                    ),
                  ),
                  row(
                    "Qorong'i mavzu",
                    null,
                    Switch(
                      value: s.dark,
                      onChanged: (bool on) =>
                          settings.update(s.copyWith(dark: on)),
                    ),
                  ),
                  row(
                    'Shrift',
                    null,
                    SegmentedButton<double>(
                      showSelectedIcon: false,
                      style: SegmentedButton.styleFrom(
                        selectedBackgroundColor: c.brand,
                        selectedForegroundColor: c.onBrand,
                        minimumSize: const Size(64, 48),
                      ),
                      segments: <ButtonSegment<double>>[
                        for (final double f in KdsSettings.fontChoices)
                          ButtonSegment<double>(
                            value: f,
                            label: Text('${(f * 100).round()}%'),
                          ),
                      ],
                      selected: <double>{s.fontScale},
                      onSelectionChanged: (Set<double> v) =>
                          settings.update(s.copyWith(fontScale: v.first)),
                    ),
                  ),
                  const Divider(height: 24),
                  row(
                    'Shoshiling (sariq)',
                    'Kutish vaqti shundan oshsa',
                    dropdown<int>(
                      s.warnAfter.inMinutes,
                      <int>{
                        ...KdsSettings.warnChoices,
                        s.warnAfter.inMinutes,
                      }.toList()..sort(),
                      (int m) => '$m daqiqa',
                      (int m) => settings.update(
                        s.copyWith(warnAfter: Duration(minutes: m)),
                      ),
                    ),
                  ),
                  row(
                    'Kechikdi (qizil)',
                    'Kutish vaqti shundan oshsa',
                    dropdown<int>(
                      s.lateAfter.inMinutes,
                      <int>{
                          ...KdsSettings.lateChoices,
                          s.lateAfter.inMinutes,
                        }.where((int m) => m > s.warnAfter.inMinutes).toList()
                        ..sort(),
                      (int m) => '$m daqiqa',
                      (int m) => settings.update(
                        s.copyWith(lateAfter: Duration(minutes: m)),
                      ),
                    ),
                  ),
                  row(
                    'Avtomatik chiqish',
                    'Ekranga shuncha vaqt tegilmasa, PIN qayta so\'raladi',
                    dropdown<Duration?>(
                      s.idleTimeout,
                      KdsSettings.idleChoices.contains(s.idleTimeout)
                          ? KdsSettings.idleChoices
                          : <Duration?>[
                              ...KdsSettings.idleChoices,
                              s.idleTimeout,
                            ],
                      _idleLabel,
                      (Duration? d) =>
                          settings.update(s.copyWith(idleTimeout: () => d)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Align(
                    alignment: Alignment.centerRight,
                    child: RzButton(
                      label: 'Yopish',
                      variant: RzButtonVariant.secondary,
                      onPressed: () => Navigator.pop(context),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
