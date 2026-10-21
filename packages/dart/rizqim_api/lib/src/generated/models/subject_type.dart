// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum SubjectType {
  @JsonValue('Order')
  order('Order'),
  @JsonValue('User')
  user('User'),
  @JsonValue('Device')
  device('Device'),
  @JsonValue('Product')
  product('Product'),
  @JsonValue('ProductVariant')
  productVariant('ProductVariant'),
  @JsonValue('Store')
  store('Store'),
  @JsonValue('Branch')
  branch('Branch'),
  @JsonValue('DevicePairingCode')
  devicePairingCode('DevicePairingCode'),
  @JsonValue('DeliveryZone')
  deliveryZone('DeliveryZone'),

  /// The name has been replaced because it contains a keyword. Original name: `null`.
  @JsonValue(null)
  valueNull(null),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const SubjectType(this.json);

  factory SubjectType.fromJson(String? json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final String? json;
  String? toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to String?. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as String?;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<SubjectType> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
