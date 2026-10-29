import 'package:flutter/material.dart';

/// Mijoz ekrani uchun yirik shriftlar: ekran bir-ikki metr (kassa) yoki bir necha metr (televizor) naridan o'qiladi.
/// Sozlamalardagi "Shrift" bularni yana kattalashtiradi (`MediaQuery.textScaler`).
abstract final class DisplayType {
  static const List<FontFeature> _tabular = <FontFeature>[
    FontFeature.tabularFigures(),
  ];

  static const TextStyle greeting = TextStyle(
    fontSize: 64,
    height: 1.1,
    fontWeight: FontWeight.w800,
  );
  static const TextStyle heading = TextStyle(
    fontSize: 30,
    height: 36 / 30,
    fontWeight: FontWeight.w700,
  );
  static const TextStyle line = TextStyle(
    fontSize: 30,
    height: 36 / 30,
    fontWeight: FontWeight.w700,
  );
  static const TextStyle lineAmount = TextStyle(
    fontSize: 30,
    height: 36 / 30,
    fontWeight: FontWeight.w700,
    fontFeatures: _tabular,
  );
  static const TextStyle modifier = TextStyle(
    fontSize: 22,
    height: 28 / 22,
    fontWeight: FontWeight.w500,
  );
  static const TextStyle label = TextStyle(
    fontSize: 24,
    height: 30 / 24,
    fontWeight: FontWeight.w600,
  );
  static const TextStyle total = TextStyle(
    fontSize: 72,
    height: 1.05,
    fontWeight: FontWeight.w800,
    fontFeatures: _tabular,
  );
  static const TextStyle thanks = TextStyle(
    fontSize: 96,
    height: 1.05,
    fontWeight: FontWeight.w800,
  );
  static const TextStyle banner = TextStyle(
    fontSize: 56,
    height: 1.1,
    fontWeight: FontWeight.w800,
    fontFeatures: _tabular,
  );

  /// Raqamlar taxtasidagi raqam: [size] — ekran o'lchamiga qarab tanlanadi.
  static TextStyle number(double size) => TextStyle(
    fontSize: size,
    height: 1.05,
    fontWeight: FontWeight.w800,
    fontFeatures: _tabular,
  );
}
