// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data273.dart';

part 'post_v1_pos_waitlist_uuid_cancel_response.g.dart';

@JsonSerializable()
class PostV1PosWaitlistUuidCancelResponse {
  const PostV1PosWaitlistUuidCancelResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosWaitlistUuidCancelResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosWaitlistUuidCancelResponseFromJson(json);

  final bool success;
  final String message;
  final Data273 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosWaitlistUuidCancelResponseToJson(this);
}
