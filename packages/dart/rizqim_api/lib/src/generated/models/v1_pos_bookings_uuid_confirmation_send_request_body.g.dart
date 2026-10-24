// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_confirmation_send_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidConfirmationSendRequestBody
_$V1PosBookingsUuidConfirmationSendRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBookingsUuidConfirmationSendRequestBody(
  channel: json['channel'] == null
      ? null
      : Channel5.fromJson(json['channel'] as String?),
);

Map<String, dynamic> _$V1PosBookingsUuidConfirmationSendRequestBodyToJson(
  V1PosBookingsUuidConfirmationSendRequestBody instance,
) => <String, dynamic>{'channel': instance.channel};
