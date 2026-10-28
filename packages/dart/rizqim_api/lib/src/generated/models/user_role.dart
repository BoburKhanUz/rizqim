// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum UserRole {
  @JsonValue('super_admin')
  superAdmin('super_admin'),
  @JsonValue('owner')
  owner('owner'),
  @JsonValue('manager')
  manager('manager'),
  @JsonValue('customer')
  customer('customer'),
  @JsonValue('courier')
  courier('courier'),
  @JsonValue('cashier')
  cashier('cashier'),
  @JsonValue('waiter')
  waiter('waiter'),
  @JsonValue('cook')
  cook('cook'),
  @JsonValue('platform')
  platform('platform'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const UserRole(this.json);

  factory UserRole.fromJson(String json) =>
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
  static List<UserRole> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
