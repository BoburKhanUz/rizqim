import 'package:flutter/material.dart';

/// Oshxona ekrani uchun kattaroq shriftlar: planshet bir-ikki metr naridan o'qiladi. Sozlamalardagi "Shrift" bularni
/// yana kattalashtiradi (`MediaQuery.textScaler`).
abstract final class KdsType {
  static const TextStyle code = TextStyle(
    fontSize: 28,
    height: 34 / 28,
    fontWeight: FontWeight.w700,
  );
  static const TextStyle timer = TextStyle(
    fontSize: 26,
    height: 32 / 26,
    fontWeight: FontWeight.w700,
    fontFeatures: <FontFeature>[FontFeature.tabularFigures()],
  );
  static const TextStyle line = TextStyle(
    fontSize: 21,
    height: 27 / 21,
    fontWeight: FontWeight.w700,
  );
  static const TextStyle quantity = TextStyle(
    fontSize: 21,
    height: 27 / 21,
    fontWeight: FontWeight.w700,
    fontFeatures: <FontFeature>[FontFeature.tabularFigures()],
  );
  static const TextStyle modifier = TextStyle(
    fontSize: 17,
    height: 22 / 17,
    fontWeight: FontWeight.w500,
  );
  static const TextStyle meta = TextStyle(
    fontSize: 15,
    height: 20 / 15,
    fontWeight: FontWeight.w500,
  );
}
