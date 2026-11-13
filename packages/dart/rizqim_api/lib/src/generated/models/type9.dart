// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum Type9 {
  /// Incorrect name has been replaced. Original name: `order.open`.
  @JsonValue('order.open')
  undefined0('order.open'),

  /// Incorrect name has been replaced. Original name: `order.round`.
  @JsonValue('order.round')
  undefined1('order.round'),

  /// Incorrect name has been replaced. Original name: `order.send`.
  @JsonValue('order.send')
  undefined2('order.send'),

  /// Incorrect name has been replaced. Original name: `order.void`.
  @JsonValue('order.void')
  undefined3('order.void'),

  /// Incorrect name has been replaced. Original name: `order.served`.
  @JsonValue('order.served')
  undefined4('order.served'),

  /// Incorrect name has been replaced. Original name: `bill.create`.
  @JsonValue('bill.create')
  undefined5('bill.create'),

  /// Incorrect name has been replaced. Original name: `bill.pay`.
  @JsonValue('bill.pay')
  undefined6('bill.pay'),

  /// Incorrect name has been replaced. Original name: `order.close`.
  @JsonValue('order.close')
  undefined7('order.close'),

  /// Incorrect name has been replaced. Original name: `order.course_send`.
  @JsonValue('order.course_send')
  undefined8('order.course_send'),

  /// Incorrect name has been replaced. Original name: `order.weight`.
  @JsonValue('order.weight')
  undefined9('order.weight'),

  /// Incorrect name has been replaced. Original name: `kitchen.bump`.
  @JsonValue('kitchen.bump')
  undefined10('kitchen.bump'),

  /// Incorrect name has been replaced. Original name: `kitchen.recall`.
  @JsonValue('kitchen.recall')
  undefined11('kitchen.recall'),

  /// Incorrect name has been replaced. Original name: `kitchen.assembled`.
  @JsonValue('kitchen.assembled')
  undefined12('kitchen.assembled'),

  /// Incorrect name has been replaced. Original name: `bill.request`.
  @JsonValue('bill.request')
  undefined13('bill.request'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const Type9(this.json);

  factory Type9.fromJson(String json) =>
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
  static List<Type9> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
