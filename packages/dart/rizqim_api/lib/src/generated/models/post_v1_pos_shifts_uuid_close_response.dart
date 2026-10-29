// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data202.dart';

part 'post_v1_pos_shifts_uuid_close_response.g.dart';

@JsonSerializable()
class PostV1PosShiftsUuidCloseResponse {
  const PostV1PosShiftsUuidCloseResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosShiftsUuidCloseResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosShiftsUuidCloseResponseFromJson(json);

  final bool success;
  final String message;
  final Data202 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosShiftsUuidCloseResponseToJson(this);
}
