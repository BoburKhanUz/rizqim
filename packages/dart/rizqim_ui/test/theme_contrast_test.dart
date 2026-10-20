import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

// The pairs and numbers of docs/DESIGN.md, "Kontrast". The Mini App measures them against its own CSS; here the same
// hex values in Dart must give the same ratios (within rounding), and every text pair must reach WCAG AA (4.5).
void main() {
  const Map<String, RzColors> themes = <String, RzColors>{
    'light': RzColors.light,
    'dark': RzColors.dark,
  };

  double soft(RzColors c, Color fg, Color softBg) =>
      contrastRatio(fg, Color.alphaBlend(softBg, c.surface));

  group('contrast helper', () {
    test('black on white is 21, same colour is 1', () {
      expect(contrastRatio(Colors.black, Colors.white), closeTo(21, 0.001));
      expect(contrastRatio(Colors.white, Colors.white), closeTo(1, 0.001));
    });
  });

  for (final MapEntry<String, RzColors> theme in themes.entries) {
    final RzColors c = theme.value;
    final bool isLight = theme.key == 'light';

    group('${theme.key} palette', () {
      test('body text reaches AA on surface and page, and matches the documented ratio', () {
        expect(
          contrastRatio(c.fg, c.surface),
          closeTo(isLight ? 17.85 : 14.87, 0.05),
        );
        expect(
          contrastRatio(c.fg, c.page),
          closeTo(isLight ? 16.37 : 16.59, 0.05),
        );
        for (final Color bg in <Color>[c.surface, c.page]) {
          expect(
            contrastRatio(c.fgMuted, bg),
            greaterThanOrEqualTo(4.5),
            reason: 'fgMuted on $bg',
          );
          expect(
            contrastRatio(c.brand, bg),
            greaterThanOrEqualTo(4.5),
            reason: 'brand text on $bg',
          );
        }
        expect(
          contrastRatio(c.fgMuted, c.surface),
          closeTo(isLight ? 5.43 : 6.71, 0.05),
        );
        expect(
          contrastRatio(c.brand, c.surface),
          closeTo(isLight ? 5.18 : 7.20, 0.05),
        );
      });

      test('solid fills carry their on-colour text (on-* / *)', () {
        final Map<String, (Color, Color, double)> pairs =
            <String, (Color, Color, double)>{
              'brand': (c.onBrand, c.brand, isLight ? 5.18 : 7.73),
              'success': (c.onSuccess, c.success, isLight ? 5.02 : 8.55),
              'warning': (c.onWarning, c.warning, isLight ? 5.02 : 10.48),
              'danger': (c.onDanger, c.danger, isLight ? 4.83 : 6.32),
              'info': (c.onInfo, c.info, isLight ? 5.17 : 7.36),
            };
        for (final MapEntry<String, (Color, Color, double)> p
            in pairs.entries) {
          final double ratio = contrastRatio(p.value.$1, p.value.$2);
          expect(ratio, greaterThanOrEqualTo(4.5), reason: p.key);
          expect(ratio, closeTo(p.value.$3, 0.05), reason: p.key);
        }
      });

      test(
        'soft badges: *-soft-fg on *-soft (over the card surface) reach AA',
        () {
          final Map<String, double> ratios = <String, double>{
            'brand': soft(c, c.brandSoftFg, c.brandSoft),
            'success': soft(c, c.successSoftFg, c.successSoft),
            'warning': soft(c, c.warningSoftFg, c.warningSoft),
            'danger': soft(c, c.dangerSoftFg, c.dangerSoft),
            'info': soft(c, c.infoSoftFg, c.infoSoft),
            'neutral': soft(c, c.fgMuted, c.surface2),
          };
          for (final MapEntry<String, double> r in ratios.entries) {
            expect(r.value, greaterThanOrEqualTo(4.5), reason: r.key);
          }
          // documented values
          expect(ratios['brand'], closeTo(isLight ? 6.60 : 7.33, 0.1));
          expect(ratios['success'], closeTo(isLight ? 6.40 : 8.72, 0.1));
          expect(ratios['warning'], closeTo(isLight ? 6.37 : 7.98, 0.1));
          expect(ratios['danger'], closeTo(isLight ? 7.28 : 7.32, 0.1));
          expect(ratios['info'], closeTo(isLight ? 7.69 : 6.84, 0.1));
          expect(ratios['neutral'], closeTo(isLight ? 4.88 : 5.67, 0.1));
        },
      );

      test('the documented exception: fgSubtle is for placeholders only (it is below AA in light)', () {
        expect(
          contrastRatio(c.fgSubtle, c.surface),
          isLight ? lessThan(4.5) : greaterThanOrEqualTo(4.5),
        );
      });
    });
  }

  group('Material theme', () {
    for (final (String name, ThemeData theme) in <(String, ThemeData)>[
      ('light', RzTheme.light()),
      ('dark', RzTheme.dark()),
    ]) {
      test('$name: Material 3, brand as primary, tokens attached', () {
        final RzColors c = theme.extension<RzColors>()!;
        expect(theme.useMaterial3, isTrue);
        expect(theme.colorScheme.primary, c.brand);
        expect(theme.colorScheme.onPrimary, c.onBrand);
        expect(theme.scaffoldBackgroundColor, c.page);
        expect(theme.colorScheme.error, c.danger);
      });

      test(
        '$name: every colour-scheme pair Material draws text with reaches AA',
        () {
          final ColorScheme s = theme.colorScheme;
          final Map<String, double> pairs = <String, double>{
            'onSurface/surface': contrastRatio(s.onSurface, s.surface),
            'onPrimary/primary': contrastRatio(s.onPrimary, s.primary),
            'onPrimaryContainer/primaryContainer': contrastRatio(
              s.onPrimaryContainer,
              s.primaryContainer,
            ),
            'onError/error': contrastRatio(s.onError, s.error),
            'onErrorContainer/errorContainer': contrastRatio(
              s.onErrorContainer,
              s.errorContainer,
            ),
            'onSecondary/secondary': contrastRatio(s.onSecondary, s.secondary),
            'onSecondaryContainer/secondaryContainer': contrastRatio(
              s.onSecondaryContainer,
              s.secondaryContainer,
            ),
            'onTertiary/tertiary': contrastRatio(s.onTertiary, s.tertiary),
            'onTertiaryContainer/tertiaryContainer': contrastRatio(
              s.onTertiaryContainer,
              s.tertiaryContainer,
            ),
            'onSurfaceVariant/surface': contrastRatio(
              s.onSurfaceVariant,
              s.surface,
            ),
            'onInverseSurface/inverseSurface': contrastRatio(
              s.onInverseSurface,
              s.inverseSurface,
            ),
          };
          pairs.forEach(
            (String pair, double ratio) =>
                expect(ratio, greaterThanOrEqualTo(4.5), reason: pair),
          );
        },
      );

      test(
        '$name: type scale never goes below 12 and uses weights 500/700 (800 only for totals)',
        () {
          final TextTheme t = theme.textTheme;
          final List<TextStyle?> used = <TextStyle?>[
            t.displaySmall,
            t.headlineSmall,
            t.titleLarge,
            t.titleMedium,
            t.titleSmall,
            t.bodyLarge,
            t.bodyMedium, //
            t.bodySmall, t.labelLarge, t.labelMedium, t.labelSmall,
          ];
          for (final TextStyle? s in used) {
            expect(s!.fontSize, greaterThanOrEqualTo(12));
          }
          expect(t.labelSmall!.fontSize, 12);
          expect(t.bodyMedium!.fontSize, 15);
          expect(t.displaySmall!.fontWeight, FontWeight.w800);
          expect(
            <TextStyle?>[
              t.titleMedium,
              t.bodyMedium,
              t.labelSmall,
            ].every((TextStyle? s) => s!.fontWeight != FontWeight.w900),
            isTrue,
          );
        },
      );

      test('$name: controls are at least 44px', () {
        expect(
          theme.filledButtonTheme.style!.minimumSize!
              .resolve(<WidgetState>{})!
              .height,
          greaterThanOrEqualTo(44),
        );
        expect(
          theme.outlinedButtonTheme.style!.minimumSize!
              .resolve(<WidgetState>{})!
              .height,
          greaterThanOrEqualTo(44),
        );
        expect(
          theme.inputDecorationTheme.constraints!.minHeight,
          greaterThanOrEqualTo(44),
        );
        expect(theme.materialTapTargetSize, MaterialTapTargetSize.padded);
      });
    }

    test('radii are 10 / 16 / 24', () {
      expect(
        <double>[RzRadius.control, RzRadius.card, RzRadius.sheet],
        <double>[10, 16, 24],
      );
    });

    test('brand colours are #C2410C (light) and #FB923C (dark)', () {
      expect(RzColors.light.brand, const Color(0xFFC2410C));
      expect(RzColors.dark.brand, const Color(0xFFFB923C));
    });
  });
}
