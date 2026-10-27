// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P4-11: the RIZQIM team's roles in the Boshqaruv markazi (platform/admin, docs/PHASE4_PLAN.md §5). A user is platform staff when users.platform_role is set. What each role may do is App\Services\Admin\PlatformRolePermissions (defaults) with the superadmin's overrides (platform_role_permissions); `superadmin` is fixed: everything. Only `superadmin` is also users.role = super_admin (the old store-level super admin, the old Filament panels); the other roles are users.role = platform, which holds no store-level permission at all.
///
@JsonEnum()
enum PlatformRole {
  @JsonValue('superadmin')
  superadmin('superadmin'),

  /// The name has been replaced because it contains a keyword. Original name: `operator`.
  @JsonValue('operator')
  valueOperator('operator'),
  @JsonValue('support')
  support('support'),
  @JsonValue('finance')
  finance('finance'),
  @JsonValue('sales')
  sales('sales'),
  @JsonValue('viewer')
  viewer('viewer'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const PlatformRole(this.json);

  factory PlatformRole.fromJson(String json) =>
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
  static List<PlatformRole> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
