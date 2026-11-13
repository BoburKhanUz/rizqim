// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket13 _$Ticket13FromJson(Map<String, dynamic> json) => Ticket13(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket13ToJson(Ticket13 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
