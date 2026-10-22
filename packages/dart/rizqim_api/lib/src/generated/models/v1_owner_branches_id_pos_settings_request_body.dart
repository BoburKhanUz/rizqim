// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pos_settings3.dart';

part 'v1_owner_branches_id_pos_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerBranchesIdPosSettingsRequestBody {
  const V1OwnerBranchesIdPosSettingsRequestBody({
    this.businessDayStartsAt,
    this.posSettings,
  });

  factory V1OwnerBranchesIdPosSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBranchesIdPosSettingsRequestBodyFromJson(json);

  @JsonKey(name: 'business_day_starts_at')
  final String? businessDayStartsAt;
  @JsonKey(name: 'pos_settings')
  final PosSettings3? posSettings;

  Map<String, Object?> toJson() =>
      _$V1OwnerBranchesIdPosSettingsRequestBodyToJson(this);
}
