// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role.dart';
import 'permission.dart';

part 'v1_owner_permissions_request_body.g.dart';

@JsonSerializable()
class V1OwnerPermissionsRequestBody {
  const V1OwnerPermissionsRequestBody({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory V1OwnerPermissionsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPermissionsRequestBodyFromJson(json);

  final Role role;
  final Permission permission;
  final bool? allowed;

  Map<String, Object?> toJson() => _$V1OwnerPermissionsRequestBodyToJson(this);
}
