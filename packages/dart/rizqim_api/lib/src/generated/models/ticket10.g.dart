// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket10 _$Ticket10FromJson(Map<String, dynamic> json) => Ticket10(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket10ToJson(Ticket10 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
