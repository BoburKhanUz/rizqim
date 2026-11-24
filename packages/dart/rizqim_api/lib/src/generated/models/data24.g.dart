// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data24 _$Data24FromJson(Map<String, dynamic> json) => Data24(
  orderUuid: json['order_uuid'] as String,
  orderNumber: json['order_number'] as String?,
  status: json['status'] as String,
  paymentUrl: json['payment_url'],
);

Map<String, dynamic> _$Data24ToJson(Data24 instance) => <String, dynamic>{
  'order_uuid': instance.orderUuid,
  'order_number': instance.orderNumber,
  'status': instance.status,
  'payment_url': instance.paymentUrl,
};
