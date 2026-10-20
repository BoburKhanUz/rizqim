// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum Type2 {
  @JsonValue('WRONG_ITEM')
  wrongItem('WRONG_ITEM'),
  @JsonValue('ORDER_NOT_RECEIVED')
  orderNotReceived('ORDER_NOT_RECEIVED'),
  @JsonValue('QUALITY_ISSUE')
  qualityIssue('QUALITY_ISSUE'),
  @JsonValue('COURIER_ISSUE')
  courierIssue('COURIER_ISSUE'),
  @JsonValue('PAYMENT_ISSUE')
  paymentIssue('PAYMENT_ISSUE'),
  @JsonValue('OTHER')
  other('OTHER'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const Type2(this.json);

  factory Type2.fromJson(String json) =>
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
  static List<Type2> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
