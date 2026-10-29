// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket12 _$Ticket12FromJson(Map<String, dynamic> json) => Ticket12(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket12ToJson(Ticket12 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
