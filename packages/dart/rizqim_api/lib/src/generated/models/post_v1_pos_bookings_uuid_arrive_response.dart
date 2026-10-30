// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data175.dart';

part 'post_v1_pos_bookings_uuid_arrive_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidArriveResponse {
  const PostV1PosBookingsUuidArriveResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidArriveResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidArriveResponseFromJson(json);

  final bool success;
  final String message;
  final Data175 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidArriveResponseToJson(this);
}
