// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_arrive_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidArriveRequestBody _$V1PosBookingsUuidArriveRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBookingsUuidArriveRequestBody(
  guests: (json['guests'] as num?)?.toInt(),
  diningTableId: (json['dining_table_id'] as num?)?.toInt(),
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBookingsUuidArriveRequestBodyToJson(
  V1PosBookingsUuidArriveRequestBody instance,
) => <String, dynamic>{
  'guests': instance.guests,
  'dining_table_id': instance.diningTableId,
  'version': instance.version,
};
