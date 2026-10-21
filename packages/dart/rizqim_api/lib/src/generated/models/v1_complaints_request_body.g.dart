// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_complaints_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1ComplaintsRequestBody _$V1ComplaintsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1ComplaintsRequestBody(
  orderId: (json['order_id'] as num).toInt(),
  type: Type3.fromJson(json['type'] as String),
  description: json['description'] as String,
);

Map<String, dynamic> _$V1ComplaintsRequestBodyToJson(
  V1ComplaintsRequestBody instance,
) => <String, dynamic>{
  'order_id': instance.orderId,
  'type': instance.type,
  'description': instance.description,
};
