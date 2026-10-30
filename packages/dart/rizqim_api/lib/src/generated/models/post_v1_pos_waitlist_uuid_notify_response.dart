// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data255.dart';

part 'post_v1_pos_waitlist_uuid_notify_response.g.dart';

@JsonSerializable()
class PostV1PosWaitlistUuidNotifyResponse {
  const PostV1PosWaitlistUuidNotifyResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosWaitlistUuidNotifyResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosWaitlistUuidNotifyResponseFromJson(json);

  final bool success;
  final String message;
  final Data255 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosWaitlistUuidNotifyResponseToJson(this);
}
