// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_me_notification_preferences_request_body.g.dart';

@JsonSerializable()
class V1MeNotificationPreferencesRequestBody {
  const V1MeNotificationPreferencesRequestBody({required this.preferences});

  factory V1MeNotificationPreferencesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1MeNotificationPreferencesRequestBodyFromJson(json);

  final List<String> preferences;

  Map<String, Object?> toJson() =>
      _$V1MeNotificationPreferencesRequestBodyToJson(this);
}
