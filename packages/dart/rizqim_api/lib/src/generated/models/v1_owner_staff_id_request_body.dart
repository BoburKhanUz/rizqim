// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffIdRequestBody {
  const V1OwnerStaffIdRequestBody({this.name, this.branchId, this.phone});

  factory V1OwnerStaffIdRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerStaffIdRequestBodyFromJson(json);

  final String? name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String? phone;

  Map<String, Object?> toJson() => _$V1OwnerStaffIdRequestBodyToJson(this);
}
