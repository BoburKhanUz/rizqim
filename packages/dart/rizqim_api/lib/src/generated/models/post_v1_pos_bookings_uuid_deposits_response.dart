// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data180.dart';

part 'post_v1_pos_bookings_uuid_deposits_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidDepositsResponse {
  const PostV1PosBookingsUuidDepositsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidDepositsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidDepositsResponseFromJson(json);

  final bool success;
  final String message;
  final Data180 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidDepositsResponseToJson(this);
}
