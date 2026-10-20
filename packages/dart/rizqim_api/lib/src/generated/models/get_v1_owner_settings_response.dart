// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data48.dart';

part 'get_v1_owner_settings_response.g.dart';

@JsonSerializable()
class GetV1OwnerSettingsResponse {
  const GetV1OwnerSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerSettingsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data48 data;

  Map<String, Object?> toJson() => _$GetV1OwnerSettingsResponseToJson(this);
}
