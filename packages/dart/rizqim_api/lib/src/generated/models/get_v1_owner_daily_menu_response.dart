// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data63.dart';

part 'get_v1_owner_daily_menu_response.g.dart';

@JsonSerializable()
class GetV1OwnerDailyMenuResponse {
  const GetV1OwnerDailyMenuResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerDailyMenuResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerDailyMenuResponseFromJson(json);

  final bool success;
  final String message;
  final Data63 data;

  Map<String, Object?> toJson() => _$GetV1OwnerDailyMenuResponseToJson(this);
}
