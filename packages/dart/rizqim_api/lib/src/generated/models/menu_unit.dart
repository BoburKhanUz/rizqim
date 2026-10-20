// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// How a product variant is sold (product_variants.unit): by the piece (portion, bottle) or by weight/volume, whose price is per kilogram or litre and whose quantity may be a fraction in steps of quantity_step (docs/MENU.md).
///
@JsonEnum()
enum MenuUnit {
  @JsonValue('piece')
  piece('piece'),
  @JsonValue('kg')
  kg('kg'),
  @JsonValue('l')
  l('l'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const MenuUnit(this.json);

  factory MenuUnit.fromJson(String json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final String? json;
  String toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to String. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as String;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<MenuUnit> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
