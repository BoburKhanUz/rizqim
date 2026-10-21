// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// What a menu item is on a receipt (products.kind, P3-11): a dish (taom, ichimlik) or a service the store sells like a dish (cooking from the guest's own food, kazan rent, a set menu service). Both need an MXIK code before a fiscal store may sell them; the kind tells the panel and the receipt which MXIK group (10202001 or 10202002) fits.
///
@JsonEnum()
enum ProductKind {
  @JsonValue('dish')
  dish('dish'),
  @JsonValue('service')
  service('service'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const ProductKind(this.json);

  factory ProductKind.fromJson(String json) =>
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
  static List<ProductKind> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
