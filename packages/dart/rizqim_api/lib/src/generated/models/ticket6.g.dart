// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket6 _$Ticket6FromJson(Map<String, dynamic> json) => Ticket6(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket6ToJson(Ticket6 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
