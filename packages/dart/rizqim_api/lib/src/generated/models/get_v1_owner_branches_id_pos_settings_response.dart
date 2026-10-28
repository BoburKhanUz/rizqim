// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data41.dart';

part 'get_v1_owner_branches_id_pos_settings_response.g.dart';

@JsonSerializable()
class GetV1OwnerBranchesIdPosSettingsResponse {
  const GetV1OwnerBranchesIdPosSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBranchesIdPosSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerBranchesIdPosSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data41 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerBranchesIdPosSettingsResponseToJson(this);
}
