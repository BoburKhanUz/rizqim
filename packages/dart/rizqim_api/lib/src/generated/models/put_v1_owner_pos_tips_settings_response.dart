// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data121.dart';

part 'put_v1_owner_pos_tips_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosTipsSettingsResponse {
  const PutV1OwnerPosTipsSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosTipsSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerPosTipsSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data121 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerPosTipsSettingsResponseToJson(this);
}
