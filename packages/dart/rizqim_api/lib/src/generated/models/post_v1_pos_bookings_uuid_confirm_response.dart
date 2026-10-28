// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data129.dart';

part 'post_v1_pos_bookings_uuid_confirm_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidConfirmResponse {
  const PostV1PosBookingsUuidConfirmResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidConfirmResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidConfirmResponseFromJson(json);

  final bool success;
  final String message;
  final Data129 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidConfirmResponseToJson(this);
}
