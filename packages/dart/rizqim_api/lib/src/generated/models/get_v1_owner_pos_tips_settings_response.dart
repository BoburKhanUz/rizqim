// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data135.dart';

part 'get_v1_owner_pos_tips_settings_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosTipsSettingsResponse {
  const GetV1OwnerPosTipsSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosTipsSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosTipsSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data135 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosTipsSettingsResponseToJson(this);
}
