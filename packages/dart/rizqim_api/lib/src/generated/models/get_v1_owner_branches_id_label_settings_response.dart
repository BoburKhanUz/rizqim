// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data55.dart';

part 'get_v1_owner_branches_id_label_settings_response.g.dart';

@JsonSerializable()
class GetV1OwnerBranchesIdLabelSettingsResponse {
  const GetV1OwnerBranchesIdLabelSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBranchesIdLabelSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerBranchesIdLabelSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data55 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerBranchesIdLabelSettingsResponseToJson(this);
}
