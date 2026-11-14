// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data138.dart';

part 'get_v1_owner_pos_banquets_ops_settings_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosBanquetsOpsSettingsResponse {
  const GetV1OwnerPosBanquetsOpsSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosBanquetsOpsSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosBanquetsOpsSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data138 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosBanquetsOpsSettingsResponseToJson(this);
}
