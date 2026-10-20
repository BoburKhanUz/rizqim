// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data22.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data22 _$Data22FromJson(Map<String, dynamic> json) => Data22(
  orderUuid: json['order_uuid'] as String,
  orderNumber: json['order_number'] as String?,
  status: json['status'] as String,
  paymentUrl: json['payment_url'],
);

Map<String, dynamic> _$Data22ToJson(Data22 instance) => <String, dynamic>{
  'order_uuid': instance.orderUuid,
  'order_number': instance.orderNumber,
  'status': instance.status,
  'payment_url': instance.paymentUrl,
};
