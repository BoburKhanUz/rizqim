// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-25 (B3): which weight a "weight later" dish's kg price is for (products.weight_mode, kept on the sold line as order_items.weight_basis): the fish as chosen, before cooking (`raw`), or as served (`cooked`).
///
@JsonEnum()
enum WeightBasis {
  @JsonValue('raw')
  raw('raw'),
  @JsonValue('cooked')
  cooked('cooked'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const WeightBasis(this.json);

  factory WeightBasis.fromJson(String json) =>
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
  static List<WeightBasis> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
