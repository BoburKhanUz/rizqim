// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data148.dart';

part 'put_v1_owner_pos_banquets_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosBanquetsSettingsResponse {
  const PutV1OwnerPosBanquetsSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosBanquetsSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerPosBanquetsSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data148 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerPosBanquetsSettingsResponseToJson(this);
}
