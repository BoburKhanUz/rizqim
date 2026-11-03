// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data56.dart';

part 'put_v1_owner_branches_id_label_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerBranchesIdLabelSettingsResponse {
  const PutV1OwnerBranchesIdLabelSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerBranchesIdLabelSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerBranchesIdLabelSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data56 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerBranchesIdLabelSettingsResponseToJson(this);
}
