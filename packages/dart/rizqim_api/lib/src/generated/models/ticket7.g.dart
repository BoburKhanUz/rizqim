// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket7 _$Ticket7FromJson(Map<String, dynamic> json) => Ticket7(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket7ToJson(Ticket7 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
