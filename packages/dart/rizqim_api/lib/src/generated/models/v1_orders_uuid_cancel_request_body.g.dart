// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_orders_uuid_cancel_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OrdersUuidCancelRequestBody _$V1OrdersUuidCancelRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OrdersUuidCancelRequestBody(
  reason: json['reason'] == null
      ? null
      : Reason.fromJson(json['reason'] as String?),
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1OrdersUuidCancelRequestBodyToJson(
  V1OrdersUuidCancelRequestBody instance,
) => <String, dynamic>{'reason': instance.reason, 'note': instance.note};
