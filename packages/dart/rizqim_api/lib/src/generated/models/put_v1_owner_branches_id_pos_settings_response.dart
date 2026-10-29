// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data47.dart';

part 'put_v1_owner_branches_id_pos_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerBranchesIdPosSettingsResponse {
  const PutV1OwnerBranchesIdPosSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerBranchesIdPosSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerBranchesIdPosSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data47 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerBranchesIdPosSettingsResponseToJson(this);
}
