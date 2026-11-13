// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket14 _$Ticket14FromJson(Map<String, dynamic> json) => Ticket14(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket14ToJson(Ticket14 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
