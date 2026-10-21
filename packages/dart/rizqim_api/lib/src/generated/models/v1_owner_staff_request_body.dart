// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role.dart';

part 'v1_owner_staff_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffRequestBody {
  const V1OwnerStaffRequestBody({
    required this.name,
    required this.role,
    required this.branchId,
    this.phone,
  });

  factory V1OwnerStaffRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerStaffRequestBodyFromJson(json);

  final String name;
  final Role role;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String? phone;

  Map<String, Object?> toJson() => _$V1OwnerStaffRequestBodyToJson(this);
}
