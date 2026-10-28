// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P4-22: what kind of food place a store application is (the public form's `format`).
///
@JsonEnum()
enum StoreFormat {
  @JsonValue('restaurant')
  restaurant('restaurant'),
  @JsonValue('cafe')
  cafe('cafe'),
  @JsonValue('fast_food')
  fastFood('fast_food'),
  @JsonValue('canteen')
  canteen('canteen'),
  @JsonValue('coffee_shop')
  coffeeShop('coffee_shop'),
  @JsonValue('bakery')
  bakery('bakery'),
  @JsonValue('teahouse')
  teahouse('teahouse'),
  @JsonValue('catering')
  catering('catering'),
  @JsonValue('other')
  other('other'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const StoreFormat(this.json);

  factory StoreFormat.fromJson(String json) =>
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
  static List<StoreFormat> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
