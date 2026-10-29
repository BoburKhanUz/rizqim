// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum Permission {
  /// Incorrect name has been replaced. Original name: `orders.manage`.
  @JsonValue('orders.manage')
  undefined0('orders.manage'),

  /// Incorrect name has been replaced. Original name: `orders.cancel`.
  @JsonValue('orders.cancel')
  undefined1('orders.cancel'),

  /// Incorrect name has been replaced. Original name: `orders.discount`.
  @JsonValue('orders.discount')
  undefined2('orders.discount'),

  /// Incorrect name has been replaced. Original name: `orders.void_item`.
  @JsonValue('orders.void_item')
  undefined3('orders.void_item'),

  /// Incorrect name has been replaced. Original name: `tables.manage`.
  @JsonValue('tables.manage')
  undefined4('tables.manage'),

  /// Incorrect name has been replaced. Original name: `pos.open_shift`.
  @JsonValue('pos.open_shift')
  undefined5('pos.open_shift'),

  /// Incorrect name has been replaced. Original name: `pos.close_shift`.
  @JsonValue('pos.close_shift')
  undefined6('pos.close_shift'),

  /// Incorrect name has been replaced. Original name: `pos.cash_in_out`.
  @JsonValue('pos.cash_in_out')
  undefined7('pos.cash_in_out'),

  /// Incorrect name has been replaced. Original name: `pos.take_payment`.
  @JsonValue('pos.take_payment')
  undefined8('pos.take_payment'),

  /// Incorrect name has been replaced. Original name: `pos.reprint_bill`.
  @JsonValue('pos.reprint_bill')
  undefined9('pos.reprint_bill'),

  /// Incorrect name has been replaced. Original name: `pos.refund`.
  @JsonValue('pos.refund')
  undefined10('pos.refund'),

  /// Incorrect name has been replaced. Original name: `bookings.manage`.
  @JsonValue('bookings.manage')
  undefined11('bookings.manage'),

  /// Incorrect name has been replaced. Original name: `customer_accounts.manage`.
  @JsonValue('customer_accounts.manage')
  undefined12('customer_accounts.manage'),

  /// Incorrect name has been replaced. Original name: `batches.manage`.
  @JsonValue('batches.manage')
  undefined13('batches.manage'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const Permission(this.json);

  factory Permission.fromJson(String json) =>
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
  static List<Permission> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
