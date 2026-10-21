// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data35.dart';

part 'patch_v1_owner_branches_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerBranchesIdResponse {
  const PatchV1OwnerBranchesIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerBranchesIdResponse.fromJson(Map<String, Object?> json) =>
      _$PatchV1OwnerBranchesIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data35 data;

  Map<String, Object?> toJson() => _$PatchV1OwnerBranchesIdResponseToJson(this);
}
