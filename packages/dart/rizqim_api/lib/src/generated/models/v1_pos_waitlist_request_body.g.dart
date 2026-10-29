// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_waitlist_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosWaitlistRequestBody _$V1PosWaitlistRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosWaitlistRequestBody(
  guestName: json['guest_name'] as String,
  partySize: (json['party_size'] as num).toInt(),
  guestPhone: json['guest_phone'] as String?,
  quotedWaitMinutes: (json['quoted_wait_minutes'] as num?)?.toInt(),
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1PosWaitlistRequestBodyToJson(
  V1PosWaitlistRequestBody instance,
) => <String, dynamic>{
  'guest_name': instance.guestName,
  'guest_phone': instance.guestPhone,
  'party_size': instance.partySize,
  'quoted_wait_minutes': instance.quotedWaitMinutes,
  'note': instance.note,
};
