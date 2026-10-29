// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data52.dart';

part 'post_v1_owner_daily_menu_copy_response.g.dart';

@JsonSerializable()
class PostV1OwnerDailyMenuCopyResponse {
  const PostV1OwnerDailyMenuCopyResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerDailyMenuCopyResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerDailyMenuCopyResponseFromJson(json);

  final bool success;
  final String message;
  final Data52 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerDailyMenuCopyResponseToJson(this);
}
