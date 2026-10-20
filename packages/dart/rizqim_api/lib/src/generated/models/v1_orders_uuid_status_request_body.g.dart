// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_orders_uuid_status_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OrdersUuidStatusRequestBody _$V1OrdersUuidStatusRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OrdersUuidStatusRequestBody(
  status: Status2.fromJson(json['status'] as String),
  courierId: (json['courier_id'] as num?)?.toInt(),
  reason: json['reason'] == null
      ? null
      : Reason2.fromJson(json['reason'] as String?),
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1OrdersUuidStatusRequestBodyToJson(
  V1OrdersUuidStatusRequestBody instance,
) => <String, dynamic>{
  'status': instance.status,
  'courier_id': instance.courierId,
  'reason': instance.reason,
  'note': instance.note,
};
