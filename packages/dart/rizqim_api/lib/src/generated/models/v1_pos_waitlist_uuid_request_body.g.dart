// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_waitlist_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosWaitlistUuidRequestBody _$V1PosWaitlistUuidRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosWaitlistUuidRequestBody(
  guestName: json['guest_name'] as String?,
  guestPhone: json['guest_phone'] as String?,
  partySize: (json['party_size'] as num?)?.toInt(),
  quotedWaitMinutes: (json['quoted_wait_minutes'] as num?)?.toInt(),
  note: json['note'] as String?,
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosWaitlistUuidRequestBodyToJson(
  V1PosWaitlistUuidRequestBody instance,
) => <String, dynamic>{
  'guest_name': instance.guestName,
  'guest_phone': instance.guestPhone,
  'party_size': instance.partySize,
  'quoted_wait_minutes': instance.quotedWaitMinutes,
  'note': instance.note,
  'version': instance.version,
};
