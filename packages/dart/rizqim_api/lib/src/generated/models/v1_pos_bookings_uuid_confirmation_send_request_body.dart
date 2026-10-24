// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel5.dart';

part 'v1_pos_bookings_uuid_confirmation_send_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidConfirmationSendRequestBody {
  const V1PosBookingsUuidConfirmationSendRequestBody({this.channel});

  factory V1PosBookingsUuidConfirmationSendRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidConfirmationSendRequestBodyFromJson(json);

  final Channel5? channel;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidConfirmationSendRequestBodyToJson(this);
}
