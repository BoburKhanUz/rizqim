// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data195.dart';

part 'post_v1_pos_bookings_uuid_confirmation_send_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidConfirmationSendResponse {
  const PostV1PosBookingsUuidConfirmationSendResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidConfirmationSendResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidConfirmationSendResponseFromJson(json);

  final bool success;
  final String message;
  final Data195 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidConfirmationSendResponseToJson(this);
}
