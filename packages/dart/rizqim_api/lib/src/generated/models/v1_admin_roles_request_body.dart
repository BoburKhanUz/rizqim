// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'platform_role.dart';
import 'admin_permission.dart';

part 'v1_admin_roles_request_body.g.dart';

@JsonSerializable()
class V1AdminRolesRequestBody {
  const V1AdminRolesRequestBody({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory V1AdminRolesRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AdminRolesRequestBodyFromJson(json);

  final PlatformRole role;
  final AdminPermission permission;
  final bool? allowed;

  Map<String, Object?> toJson() => _$V1AdminRolesRequestBodyToJson(this);
}
