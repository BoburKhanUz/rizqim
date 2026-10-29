// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_waitlist_uuid_cancel_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosWaitlistUuidCancelRequestBody _$V1PosWaitlistUuidCancelRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosWaitlistUuidCancelRequestBody(
  reason: json['reason'] as String?,
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosWaitlistUuidCancelRequestBodyToJson(
  V1PosWaitlistUuidCancelRequestBody instance,
) => <String, dynamic>{'reason': instance.reason, 'version': instance.version};
