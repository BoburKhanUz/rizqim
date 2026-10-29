// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket8 _$Ticket8FromJson(Map<String, dynamic> json) => Ticket8(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket8ToJson(Ticket8 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
