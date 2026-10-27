// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P4-11: what a RIZQIM team member may do in the Boshqaruv markazi (admin API /api/v1/admin, `admin.can:` middleware). Separate from the store-level App\Enums\Permission: an admin permission never lets anyone act inside a store as its staff. Defaults per platform role: App\Services\Admin\PlatformRolePermissions; the superadmin may change them. Stage B sections (Bugun, Buyurtmalar, Monitoring...) use the entries that are already here.
///
@JsonEnum()
enum AdminPermission {
  /// Incorrect name has been replaced. Original name: `admin.today.view`.
  @JsonValue('admin.today.view')
  undefined0('admin.today.view'),

  /// Incorrect name has been replaced. Original name: `admin.orders.view`.
  @JsonValue('admin.orders.view')
  undefined1('admin.orders.view'),

  /// Incorrect name has been replaced. Original name: `admin.orders.intervene`.
  @JsonValue('admin.orders.intervene')
  undefined2('admin.orders.intervene'),

  /// Incorrect name has been replaced. Original name: `admin.orders.refund_request`.
  @JsonValue('admin.orders.refund_request')
  undefined3('admin.orders.refund_request'),

  /// Incorrect name has been replaced. Original name: `admin.orders.refund_approve`.
  @JsonValue('admin.orders.refund_approve')
  undefined4('admin.orders.refund_approve'),

  /// Incorrect name has been replaced. Original name: `admin.stores.view`.
  @JsonValue('admin.stores.view')
  undefined5('admin.stores.view'),

  /// Incorrect name has been replaced. Original name: `admin.stores.manage`.
  @JsonValue('admin.stores.manage')
  undefined6('admin.stores.manage'),

  /// Incorrect name has been replaced. Original name: `admin.stores.impersonate`.
  @JsonValue('admin.stores.impersonate')
  undefined7('admin.stores.impersonate'),

  /// Incorrect name has been replaced. Original name: `admin.finance.view`.
  @JsonValue('admin.finance.view')
  undefined8('admin.finance.view'),

  /// Incorrect name has been replaced. Original name: `admin.finance.manage`.
  @JsonValue('admin.finance.manage')
  undefined9('admin.finance.manage'),

  /// Incorrect name has been replaced. Original name: `admin.payouts.approve`.
  @JsonValue('admin.payouts.approve')
  undefined10('admin.payouts.approve'),

  /// Incorrect name has been replaced. Original name: `admin.subscriptions.manage`.
  @JsonValue('admin.subscriptions.manage')
  undefined11('admin.subscriptions.manage'),

  /// Incorrect name has been replaced. Original name: `admin.devices.view`.
  @JsonValue('admin.devices.view')
  undefined12('admin.devices.view'),

  /// Incorrect name has been replaced. Original name: `admin.monitoring.view`.
  @JsonValue('admin.monitoring.view')
  undefined13('admin.monitoring.view'),

  /// Incorrect name has been replaced. Original name: `admin.monitoring.manage`.
  @JsonValue('admin.monitoring.manage')
  undefined14('admin.monitoring.manage'),

  /// Incorrect name has been replaced. Original name: `admin.support.view`.
  @JsonValue('admin.support.view')
  undefined15('admin.support.view'),

  /// Incorrect name has been replaced. Original name: `admin.support.manage`.
  @JsonValue('admin.support.manage')
  undefined16('admin.support.manage'),

  /// Incorrect name has been replaced. Original name: `admin.users.view`.
  @JsonValue('admin.users.view')
  undefined17('admin.users.view'),

  /// Incorrect name has been replaced. Original name: `admin.marketing.view`.
  @JsonValue('admin.marketing.view')
  undefined18('admin.marketing.view'),

  /// Incorrect name has been replaced. Original name: `admin.marketing.manage`.
  @JsonValue('admin.marketing.manage')
  undefined19('admin.marketing.manage'),

  /// Incorrect name has been replaced. Original name: `admin.integrations.view`.
  @JsonValue('admin.integrations.view')
  undefined20('admin.integrations.view'),

  /// Incorrect name has been replaced. Original name: `admin.integrations.manage`.
  @JsonValue('admin.integrations.manage')
  undefined21('admin.integrations.manage'),

  /// Incorrect name has been replaced. Original name: `admin.system.manage`.
  @JsonValue('admin.system.manage')
  undefined22('admin.system.manage'),

  /// Incorrect name has been replaced. Original name: `admin.team.manage`.
  @JsonValue('admin.team.manage')
  undefined23('admin.team.manage'),

  /// Incorrect name has been replaced. Original name: `admin.audit.view`.
  @JsonValue('admin.audit.view')
  undefined24('admin.audit.view'),

  /// Incorrect name has been replaced. Original name: `admin.pii.view`.
  @JsonValue('admin.pii.view')
  undefined25('admin.pii.view'),

  /// Incorrect name has been replaced. Original name: `admin.export`.
  @JsonValue('admin.export')
  undefined26('admin.export'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const AdminPermission(this.json);

  factory AdminPermission.fromJson(String json) =>
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
  static List<AdminPermission> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
