// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_cancel_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidCancelRequestBody _$V1PosBookingsUuidCancelRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBookingsUuidCancelRequestBody(
  reason: json['reason'] as String,
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBookingsUuidCancelRequestBodyToJson(
  V1PosBookingsUuidCancelRequestBody instance,
) => <String, dynamic>{'reason': instance.reason, 'version': instance.version};
