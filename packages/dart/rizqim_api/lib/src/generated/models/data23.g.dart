// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data23.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data23 _$Data23FromJson(Map<String, dynamic> json) => Data23(
  orderUuid: json['order_uuid'] as String,
  orderNumber: json['order_number'] as String?,
  status: json['status'] as String,
  paymentUrl: json['payment_url'],
);

Map<String, dynamic> _$Data23ToJson(Data23 instance) => <String, dynamic>{
  'order_uuid': instance.orderUuid,
  'order_number': instance.orderNumber,
  'status': instance.status,
  'payment_url': instance.paymentUrl,
};
