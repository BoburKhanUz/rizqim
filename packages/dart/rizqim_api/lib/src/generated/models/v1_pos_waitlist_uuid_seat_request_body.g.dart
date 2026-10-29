// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_waitlist_uuid_seat_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosWaitlistUuidSeatRequestBody _$V1PosWaitlistUuidSeatRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosWaitlistUuidSeatRequestBody(
  diningTableId: (json['dining_table_id'] as num).toInt(),
  openOrder: json['open_order'] as bool?,
  waiterId: (json['waiter_id'] as num?)?.toInt(),
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosWaitlistUuidSeatRequestBodyToJson(
  V1PosWaitlistUuidSeatRequestBody instance,
) => <String, dynamic>{
  'dining_table_id': instance.diningTableId,
  'open_order': instance.openOrder,
  'waiter_id': instance.waiterId,
  'version': instance.version,
};
