// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data132.dart';

part 'patch_v1_owner_staff_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerStaffIdResponse {
  const PatchV1OwnerStaffIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerStaffIdResponse.fromJson(Map<String, Object?> json) =>
      _$PatchV1OwnerStaffIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data132 data;

  Map<String, Object?> toJson() => _$PatchV1OwnerStaffIdResponseToJson(this);
}
