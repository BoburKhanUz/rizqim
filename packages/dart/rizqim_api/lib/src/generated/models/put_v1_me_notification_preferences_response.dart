// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data41.dart';

part 'put_v1_me_notification_preferences_response.g.dart';

@JsonSerializable()
class PutV1MeNotificationPreferencesResponse {
  const PutV1MeNotificationPreferencesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1MeNotificationPreferencesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1MeNotificationPreferencesResponseFromJson(json);

  final bool success;
  final String message;
  final Data41 data;

  Map<String, Object?> toJson() =>
      _$PutV1MeNotificationPreferencesResponseToJson(this);
}
