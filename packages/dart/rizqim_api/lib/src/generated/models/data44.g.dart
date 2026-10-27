// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data44.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data44 _$Data44FromJson(Map<String, dynamic> json) => Data44(
  orderUuid: json['order_uuid'] as String,
  orderNumber: json['order_number'] as String?,
  status: json['status'] as String,
  paymentUrl: json['payment_url'],
);

Map<String, dynamic> _$Data44ToJson(Data44 instance) => <String, dynamic>{
  'order_uuid': instance.orderUuid,
  'order_number': instance.orderNumber,
  'status': instance.status,
  'payment_url': instance.paymentUrl,
};
