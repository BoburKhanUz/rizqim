import 'package:flutter/material.dart';

/// Rizqim rang tokenlari (docs/DESIGN.md, "Palitra"). Mini App'dagi `--rz-*` o'zgaruvchilar bilan bir xil nomlar.
///
/// Qoida: rangli *matn* uchun `*SoftFg` (soft fon ustida) yoki [brand] ishlating; `success`, `warning`, `danger`,
/// `info` solid ranglari faqat fon (`on*` matn bilan), ikonka va chegara uchun. [accent] faqat bezak: ostida matn yo'q.
/// [fgSubtle] faqat placeholder va o'chirilgan elementlar uchun.
@immutable
class RzColors extends ThemeExtension<RzColors> {
  const RzColors({
    required this.page,
    required this.surface,
    required this.surface2,
    required this.line,
    required this.fg,
    required this.fgMuted,
    required this.fgSubtle,
    required this.brand,
    required this.onBrand,
    required this.brandSoft,
    required this.brandSoftFg,
    required this.accent,
    required this.success,
    required this.onSuccess,
    required this.successSoft,
    required this.successSoftFg,
    required this.warning,
    required this.onWarning,
    required this.warningSoft,
    required this.warningSoftFg,
    required this.danger,
    required this.onDanger,
    required this.dangerSoft,
    required this.dangerSoftFg,
    required this.info,
    required this.onInfo,
    required this.infoSoft,
    required this.infoSoftFg,
    required this.scrim,
  });

  /// Yorug' mavzu.
  static const RzColors light = RzColors(
    page: Color(0xFFF4F5F7),
    surface: Color(0xFFFFFFFF),
    surface2: Color(0xFFF1F3F5),
    line: Color(0x140F172A), // rgba(15,23,42,.08)
    fg: Color(0xFF0F172A),
    fgMuted: Color(0xFF5F6B7A),
    fgSubtle: Color(0xFF94A3B8),
    brand: Color(0xFFC2410C),
    onBrand: Color(0xFFFFFFFF),
    brandSoft: Color(0xFFFFF1E6),
    brandSoftFg: Color(0xFF9A3412),
    accent: Color(0xFFF59E0B),
    success: Color(0xFF15803D),
    onSuccess: Color(0xFFFFFFFF),
    successSoft: Color(0xFFE8F6EE),
    successSoftFg: Color(0xFF166534),
    warning: Color(0xFFB45309),
    onWarning: Color(0xFFFFFFFF),
    warningSoft: Color(0xFFFEF3C7),
    warningSoftFg: Color(0xFF92400E),
    danger: Color(0xFFDC2626),
    onDanger: Color(0xFFFFFFFF),
    dangerSoft: Color(0xFFFDECEC),
    dangerSoftFg: Color(0xFF991B1B),
    info: Color(0xFF2563EB),
    onInfo: Color(0xFFFFFFFF),
    infoSoft: Color(0xFFEAF1FE),
    infoSoftFg: Color(0xFF1E40AF),
    scrim: Color(0x800F172A), // rgba(15,23,42,.5)
  );

  /// Qorong'i mavzu. Solid fon ustidagi matn (`on*`) qorong'i, oq emas.
  static const RzColors dark = RzColors(
    page: Color(0xFF0E1621),
    surface: Color(0xFF17212B),
    surface2: Color(0x0FFFFFFF), // rgba(255,255,255,.06)
    line: Color(0x14FFFFFF), // rgba(255,255,255,.08)
    fg: Color(0xFFF1F5F9),
    fgMuted: Color(0xFF9AA8B6),
    fgSubtle: Color(0xFF7D8B99),
    brand: Color(0xFFFB923C),
    onBrand: Color(0xFF1C1917),
    brandSoft: Color(0x29FB923C), // rgba(251,146,60,.16)
    brandSoftFg: Color(0xFFFDBA74),
    accent: Color(0xFFFBBF24),
    success: Color(0xFF4ADE80),
    onSuccess: Color(0xFF052E16),
    successSoft: Color(0x2922C55E), // rgba(34,197,94,.16)
    successSoftFg: Color(0xFF86EFAC),
    warning: Color(0xFFFBBF24),
    onWarning: Color(0xFF1C1917),
    warningSoft: Color(0x29FBBF24), // rgba(251,191,36,.16)
    warningSoftFg: Color(0xFFFCD34D),
    danger: Color(0xFFF87171),
    onDanger: Color(0xFF1C1917),
    dangerSoft: Color(0x29EF4444), // rgba(239,68,68,.16)
    dangerSoftFg: Color(0xFFFCA5A5),
    info: Color(0xFF60A5FA),
    onInfo: Color(0xFF0B1220),
    infoSoft: Color(0x2960A5FA), // rgba(96,165,250,.16)
    infoSoftFg: Color(0xFF93C5FD),
    scrim: Color(0x99000000), // rgba(0,0,0,.6)
  );

  final Color page;
  final Color surface;
  final Color surface2;
  final Color line;
  final Color fg;
  final Color fgMuted;
  final Color fgSubtle;
  final Color brand;
  final Color onBrand;
  final Color brandSoft;
  final Color brandSoftFg;
  final Color accent;
  final Color success;
  final Color onSuccess;
  final Color successSoft;
  final Color successSoftFg;
  final Color warning;
  final Color onWarning;
  final Color warningSoft;
  final Color warningSoftFg;
  final Color danger;
  final Color onDanger;
  final Color dangerSoft;
  final Color dangerSoftFg;
  final Color info;
  final Color onInfo;
  final Color infoSoft;
  final Color infoSoftFg;
  final Color scrim;

  List<Color> get _all => <Color>[
    page,
    surface,
    surface2,
    line,
    fg,
    fgMuted,
    fgSubtle,
    brand,
    onBrand,
    brandSoft,
    brandSoftFg,
    accent,
    success, //
    onSuccess,
    successSoft,
    successSoftFg,
    warning,
    onWarning,
    warningSoft,
    warningSoftFg,
    danger,
    onDanger, //
    dangerSoft, dangerSoftFg, info, onInfo, infoSoft, infoSoftFg, scrim,
  ];

  @override
  RzColors copyWith() => this;

  @override
  RzColors lerp(ThemeExtension<RzColors>? other, double t) {
    if (other is! RzColors) return this;
    final List<Color> a = _all;
    final List<Color> b = other._all;
    Color at(int i) => Color.lerp(a[i], b[i], t)!;
    return RzColors(
      page: at(0),
      surface: at(1),
      surface2: at(2),
      line: at(3),
      fg: at(4),
      fgMuted: at(5),
      fgSubtle: at(6), //
      brand: at(7),
      onBrand: at(8),
      brandSoft: at(9),
      brandSoftFg: at(10),
      accent: at(11),
      success: at(12), //
      onSuccess: at(13),
      successSoft: at(14),
      successSoftFg: at(15),
      warning: at(16),
      onWarning: at(17), //
      warningSoft: at(18),
      warningSoftFg: at(19),
      danger: at(20),
      onDanger: at(21),
      dangerSoft: at(22), //
      dangerSoftFg: at(23),
      info: at(24),
      onInfo: at(25),
      infoSoft: at(26),
      infoSoftFg: at(27),
      scrim: at(28),
    );
  }
}

extension RzColorsContext on BuildContext {
  /// Joriy mavzuning Rizqim ranglari. [RzTheme] ulanmagan bo'lsa yorug' to'plam.
  RzColors get rz => Theme.of(this).extension<RzColors>() ?? RzColors.light;
}
