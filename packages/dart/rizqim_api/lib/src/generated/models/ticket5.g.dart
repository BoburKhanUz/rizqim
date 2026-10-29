// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket5 _$Ticket5FromJson(Map<String, dynamic> json) => Ticket5(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket5ToJson(Ticket5 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
