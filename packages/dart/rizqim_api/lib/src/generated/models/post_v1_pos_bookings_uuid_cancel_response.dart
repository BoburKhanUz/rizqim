// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data127.dart';

part 'post_v1_pos_bookings_uuid_cancel_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidCancelResponse {
  const PostV1PosBookingsUuidCancelResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidCancelResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidCancelResponseFromJson(json);

  final bool success;
  final String message;
  final Data127 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidCancelResponseToJson(this);
}
