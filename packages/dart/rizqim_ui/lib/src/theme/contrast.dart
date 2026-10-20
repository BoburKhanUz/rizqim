import 'package:flutter/painting.dart';

/// WCAG 2.x kontrast nisbati (1..21). Matn uchun kamida 4.5 (docs/DESIGN.md, "Kontrast").
///
/// [foreground] shaffof bo'lsa, [background] ustiga aralashtiriladi. Shaffof [background] ni chaqiruvchi avval
/// o'zi bilan ostidagi fon bilan aralashtirishi kerak (`Color.alphaBlend`).
double contrastRatio(Color foreground, Color background) {
  final Color fg = foreground.a < 1
      ? Color.alphaBlend(foreground, background)
      : foreground;
  final double a = fg.computeLuminance();
  final double b = background.computeLuminance();
  final double lighter = a > b ? a : b;
  final double darker = a > b ? b : a;
  return (lighter + 0.05) / (darker + 0.05);
}
