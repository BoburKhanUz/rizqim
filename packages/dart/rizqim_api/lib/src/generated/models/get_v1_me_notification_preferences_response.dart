// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data18.dart';

part 'get_v1_me_notification_preferences_response.g.dart';

@JsonSerializable()
class GetV1MeNotificationPreferencesResponse {
  const GetV1MeNotificationPreferencesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1MeNotificationPreferencesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1MeNotificationPreferencesResponseFromJson(json);

  final bool success;
  final String message;
  final Data18 data;

  Map<String, Object?> toJson() =>
      _$GetV1MeNotificationPreferencesResponseToJson(this);
}
