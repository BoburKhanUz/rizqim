// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data276.dart';

part 'post_v1_pos_waitlist_uuid_seat_response.g.dart';

@JsonSerializable()
class PostV1PosWaitlistUuidSeatResponse {
  const PostV1PosWaitlistUuidSeatResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosWaitlistUuidSeatResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosWaitlistUuidSeatResponseFromJson(json);

  final bool success;
  final String message;
  final Data276 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosWaitlistUuidSeatResponseToJson(this);
}
