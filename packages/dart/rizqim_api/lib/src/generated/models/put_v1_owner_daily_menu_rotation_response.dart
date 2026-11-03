// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data66.dart';

part 'put_v1_owner_daily_menu_rotation_response.g.dart';

@JsonSerializable()
class PutV1OwnerDailyMenuRotationResponse {
  const PutV1OwnerDailyMenuRotationResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerDailyMenuRotationResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerDailyMenuRotationResponseFromJson(json);

  final bool success;
  final String message;
  final Data66 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerDailyMenuRotationResponseToJson(this);
}
