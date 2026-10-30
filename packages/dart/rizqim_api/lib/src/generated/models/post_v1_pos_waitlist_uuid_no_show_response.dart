// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data254.dart';

part 'post_v1_pos_waitlist_uuid_no_show_response.g.dart';

@JsonSerializable()
class PostV1PosWaitlistUuidNoShowResponse {
  const PostV1PosWaitlistUuidNoShowResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosWaitlistUuidNoShowResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosWaitlistUuidNoShowResponseFromJson(json);

  final bool success;
  final String message;
  final Data254 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosWaitlistUuidNoShowResponseToJson(this);
}
