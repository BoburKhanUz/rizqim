// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data202.dart';

part 'post_v1_pos_bookings_uuid_no_show_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidNoShowResponse {
  const PostV1PosBookingsUuidNoShowResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidNoShowResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidNoShowResponseFromJson(json);

  final bool success;
  final String message;
  final Data202 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidNoShowResponseToJson(this);
}
