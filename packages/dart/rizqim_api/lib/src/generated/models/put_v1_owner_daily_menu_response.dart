// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data51.dart';

part 'put_v1_owner_daily_menu_response.g.dart';

@JsonSerializable()
class PutV1OwnerDailyMenuResponse {
  const PutV1OwnerDailyMenuResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerDailyMenuResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerDailyMenuResponseFromJson(json);

  final bool success;
  final String message;
  final Data51 data;

  Map<String, Object?> toJson() => _$PutV1OwnerDailyMenuResponseToJson(this);
}
