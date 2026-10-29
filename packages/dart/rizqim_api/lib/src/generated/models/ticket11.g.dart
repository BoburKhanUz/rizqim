// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket11 _$Ticket11FromJson(Map<String, dynamic> json) => Ticket11(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket11ToJson(Ticket11 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
