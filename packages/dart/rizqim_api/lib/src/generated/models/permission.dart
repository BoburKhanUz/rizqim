// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// What a store staff member may do. Each role gets a default set (App\Services\Auth\RolePermissions) that an owner can adjust per store (role_permission_overrides); App\Services\Auth\PermissionService resolves the effective set, and the Gate answers `$user->can('orders.cancel', $order)` with it (branch scoping included).
///
@JsonEnum()
enum Permission {
  /// Incorrect name has been replaced. Original name: `orders.view`.
  @JsonValue('orders.view')
  undefined0('orders.view'),

  /// Incorrect name has been replaced. Original name: `orders.manage`.
  @JsonValue('orders.manage')
  undefined1('orders.manage'),

  /// Incorrect name has been replaced. Original name: `orders.cancel`.
  @JsonValue('orders.cancel')
  undefined2('orders.cancel'),

  /// Incorrect name has been replaced. Original name: `orders.discount`.
  @JsonValue('orders.discount')
  undefined3('orders.discount'),

  /// Incorrect name has been replaced. Original name: `orders.void_item`.
  @JsonValue('orders.void_item')
  undefined4('orders.void_item'),

  /// Incorrect name has been replaced. Original name: `orders.assign_courier`.
  @JsonValue('orders.assign_courier')
  undefined5('orders.assign_courier'),

  /// Incorrect name has been replaced. Original name: `orders.refund`.
  @JsonValue('orders.refund')
  undefined6('orders.refund'),

  /// Incorrect name has been replaced. Original name: `kitchen.manage`.
  @JsonValue('kitchen.manage')
  undefined7('kitchen.manage'),

  /// Incorrect name has been replaced. Original name: `deliveries.manage`.
  @JsonValue('deliveries.manage')
  undefined8('deliveries.manage'),

  /// Incorrect name has been replaced. Original name: `tables.manage`.
  @JsonValue('tables.manage')
  undefined9('tables.manage'),

  /// Incorrect name has been replaced. Original name: `menu.view`.
  @JsonValue('menu.view')
  undefined10('menu.view'),

  /// Incorrect name has been replaced. Original name: `menu.manage`.
  @JsonValue('menu.manage')
  undefined11('menu.manage'),

  /// Incorrect name has been replaced. Original name: `stoplist.manage`.
  @JsonValue('stoplist.manage')
  undefined12('stoplist.manage'),

  /// Incorrect name has been replaced. Original name: `batches.manage`.
  @JsonValue('batches.manage')
  undefined13('batches.manage'),

  /// Incorrect name has been replaced. Original name: `staff.view`.
  @JsonValue('staff.view')
  undefined14('staff.view'),

  /// Incorrect name has been replaced. Original name: `staff.manage`.
  @JsonValue('staff.manage')
  undefined15('staff.manage'),

  /// Incorrect name has been replaced. Original name: `staff.pin`.
  @JsonValue('staff.pin')
  undefined16('staff.pin'),

  /// Incorrect name has been replaced. Original name: `devices.view`.
  @JsonValue('devices.view')
  undefined17('devices.view'),

  /// Incorrect name has been replaced. Original name: `devices.manage`.
  @JsonValue('devices.manage')
  undefined18('devices.manage'),

  /// Incorrect name has been replaced. Original name: `reports.view`.
  @JsonValue('reports.view')
  undefined19('reports.view'),

  /// Incorrect name has been replaced. Original name: `finance.view`.
  @JsonValue('finance.view')
  undefined20('finance.view'),

  /// Incorrect name has been replaced. Original name: `finance.manage`.
  @JsonValue('finance.manage')
  undefined21('finance.manage'),

  /// Incorrect name has been replaced. Original name: `settings.manage`.
  @JsonValue('settings.manage')
  undefined22('settings.manage'),

  /// Incorrect name has been replaced. Original name: `zones.manage`.
  @JsonValue('zones.manage')
  undefined23('zones.manage'),

  /// Incorrect name has been replaced. Original name: `audit.view`.
  @JsonValue('audit.view')
  undefined24('audit.view'),

  /// Incorrect name has been replaced. Original name: `permissions.manage`.
  @JsonValue('permissions.manage')
  undefined25('permissions.manage'),

  /// Incorrect name has been replaced. Original name: `pos.open_shift`.
  @JsonValue('pos.open_shift')
  undefined26('pos.open_shift'),

  /// Incorrect name has been replaced. Original name: `pos.close_shift`.
  @JsonValue('pos.close_shift')
  undefined27('pos.close_shift'),

  /// Incorrect name has been replaced. Original name: `pos.cash_in_out`.
  @JsonValue('pos.cash_in_out')
  undefined28('pos.cash_in_out'),

  /// Incorrect name has been replaced. Original name: `pos.take_payment`.
  @JsonValue('pos.take_payment')
  undefined29('pos.take_payment'),

  /// Incorrect name has been replaced. Original name: `pos.reprint_bill`.
  @JsonValue('pos.reprint_bill')
  undefined30('pos.reprint_bill'),

  /// Incorrect name has been replaced. Original name: `branches.manage`.
  @JsonValue('branches.manage')
  undefined31('branches.manage'),

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
