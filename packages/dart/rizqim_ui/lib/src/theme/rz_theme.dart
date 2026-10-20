import 'package:flutter/material.dart';

import 'rz_colors.dart';

/// Radiuslar (docs/DESIGN.md): `control` input va chip, `card` kartochka va tugma, `sheet` sheet va dialog.
abstract final class RzRadius {
  static const double control = 10;
  static const double card = 16;
  static const double sheet = 24;
}

/// Bosiladigan elementning eng kichik o'lchami: `md` 44, `lg` 52 (docs/DESIGN.md, "Komponentlar").
abstract final class RzTouch {
  static const double min = 44;
  static const double large = 52;
}

/// Shrift o'lchamlari (docs/DESIGN.md, "Tipografika"). Eng kichigi 12.
abstract final class RzType {
  static const TextStyle caption = TextStyle(
    fontSize: 12,
    height: 16 / 12,
    fontWeight: FontWeight.w500,
  );
  static const TextStyle footnote = TextStyle(
    fontSize: 13,
    height: 18 / 13,
    fontWeight: FontWeight.w500,
  );
  static const TextStyle body = TextStyle(
    fontSize: 15,
    height: 20 / 15,
    fontWeight: FontWeight.w500,
  );
  static const TextStyle headline = TextStyle(
    fontSize: 17,
    height: 22 / 17,
    fontWeight: FontWeight.w700,
  );
  static const TextStyle title = TextStyle(
    fontSize: 20,
    height: 26 / 20,
    fontWeight: FontWeight.w700,
  );
  static const TextStyle display = TextStyle(
    fontSize: 28,
    height: 34 / 28,
    fontWeight: FontWeight.w800,
  );
}

/// Rizqim Material 3 mavzusi.
abstract final class RzTheme {
  static ThemeData light() => _build(RzColors.light, Brightness.light);

  static ThemeData dark() => _build(RzColors.dark, Brightness.dark);

  static ThemeData _build(RzColors c, Brightness brightness) {
    // Yarim shaffof tokenlar (qorong'i `surface2`, `*Soft`) Material rang sxemasiga tayyor rang bo'lib kiradi
    final Color surface2 = Color.alphaBlend(c.surface2, c.surface);
    final ColorScheme scheme = ColorScheme(
      brightness: brightness,
      primary: c.brand,
      onPrimary: c.onBrand,
      primaryContainer: Color.alphaBlend(c.brandSoft, c.surface),
      onPrimaryContainer: c.brandSoftFg,
      secondary: c.brandSoftFg,
      onSecondary: Color.alphaBlend(c.brandSoft, c.surface),
      secondaryContainer: surface2,
      onSecondaryContainer: c.fg,
      tertiary: c.info,
      onTertiary: c.onInfo,
      tertiaryContainer: Color.alphaBlend(c.infoSoft, c.surface),
      onTertiaryContainer: c.infoSoftFg,
      error: c.danger,
      onError: c.onDanger,
      errorContainer: Color.alphaBlend(c.dangerSoft, c.surface),
      onErrorContainer: c.dangerSoftFg,
      surface: c.surface,
      onSurface: c.fg,
      onSurfaceVariant: c.fgMuted,
      surfaceDim: c.page,
      surfaceBright: c.surface,
      surfaceContainerLowest: c.surface,
      surfaceContainerLow: c.surface,
      surfaceContainer: surface2,
      surfaceContainerHigh: surface2,
      surfaceContainerHighest: surface2,
      outline: c.fgMuted,
      outlineVariant: c.line,
      shadow: const Color(0xFF000000),
      scrim: c.scrim,
      inverseSurface: c.fg,
      onInverseSurface: c.surface,
      inversePrimary: c.brandSoftFg,
    );

    final TextTheme text = _textTheme(c);
    const Size minButton = Size(RzTouch.min, RzTouch.min);
    final RoundedRectangleBorder cardShape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(RzRadius.card),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: c.page,
      canvasColor: c.surface,
      dividerColor: c.line,
      textTheme: text,
      primaryTextTheme: text,
      extensions: <ThemeExtension<dynamic>>[c],
      // Barcha bosiladigan elementlar kamida 44px (planshetda barmoq bilan)
      materialTapTargetSize: MaterialTapTargetSize.padded,
      visualDensity: VisualDensity.standard,
      appBarTheme: AppBarTheme(
        backgroundColor: c.surface,
        foregroundColor: c.fg,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleTextStyle: RzType.title.copyWith(color: c.fg),
      ),
      cardTheme: CardThemeData(
        color: c.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.card),
          side: BorderSide(color: c.line),
        ),
      ),
      dividerTheme: DividerThemeData(color: c.line, space: 1, thickness: 1),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: minButton,
          shape: cardShape,
          textStyle: RzType.body,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: minButton,
          shape: cardShape,
          textStyle: RzType.body,
          side: BorderSide(color: c.fgMuted.withValues(alpha: 0.5)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: minButton,
          shape: cardShape,
          textStyle: RzType.body,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(minimumSize: minButton),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface2,
        hintStyle: RzType.body.copyWith(color: c.fgSubtle),
        labelStyle: RzType.footnote.copyWith(color: c.fgMuted),
        errorStyle: RzType.caption.copyWith(color: c.dangerSoftFg),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 12,
        ),
        constraints: const BoxConstraints(minHeight: RzTouch.min),
        border: _inputBorder(Colors.transparent),
        enabledBorder: _inputBorder(Colors.transparent),
        focusedBorder: _inputBorder(c.brand, width: 2),
        errorBorder: _inputBorder(c.danger),
        focusedErrorBorder: _inputBorder(c.danger, width: 2),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: c.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.sheet),
        ),
        titleTextStyle: RzType.title.copyWith(color: c.fg),
        contentTextStyle: RzType.body.copyWith(color: c.fg),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: c.surface,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(RzRadius.sheet),
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: surface2,
        labelStyle: RzType.footnote.copyWith(color: c.fg),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        side: BorderSide.none,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: c.fg,
        contentTextStyle: RzType.body.copyWith(color: c.surface),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: c.brand),
    );
  }

  static OutlineInputBorder _inputBorder(Color color, {double width = 1}) =>
      OutlineInputBorder(
        borderRadius: BorderRadius.circular(RzRadius.control),
        borderSide: BorderSide(color: color, width: width),
      );

  static TextTheme _textTheme(RzColors c) {
    TextStyle s(TextStyle base, [Color? color]) =>
        base.copyWith(color: color ?? c.fg);
    return TextTheme(
      displaySmall: s(RzType.display),
      headlineSmall: s(RzType.title),
      titleLarge: s(RzType.title),
      titleMedium: s(RzType.headline),
      titleSmall: s(RzType.body),
      bodyLarge: s(RzType.headline.copyWith(fontWeight: FontWeight.w500)),
      bodyMedium: s(RzType.body),
      bodySmall: s(RzType.footnote, c.fgMuted),
      labelLarge: s(RzType.body),
      labelMedium: s(RzType.footnote),
      labelSmall: s(RzType.caption, c.fgMuted),
    );
  }
}
