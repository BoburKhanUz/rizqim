// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum Kind4 {
  @JsonValue('header')
  header('header'),
  @JsonValue('place')
  place('place'),
  @JsonValue('time')
  time('time'),
  @JsonValue('room')
  room('room'),
  @JsonValue('room_fee')
  roomFee('room_fee'),
  @JsonValue('min_spend')
  minSpend('min_spend'),
  @JsonValue('service_charge')
  serviceCharge('service_charge'),
  @JsonValue('place_fee')
  placeFee('place_fee'),
  @JsonValue('pre_order')
  preOrder('pre_order'),
  @JsonValue('deposit')
  deposit('deposit'),
  @JsonValue('cancel_policy')
  cancelPolicy('cancel_policy'),
  @JsonValue('contact')
  contact('contact'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const Kind4(this.json);

  factory Kind4.fromJson(String json) =>
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
  static List<Kind4> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
