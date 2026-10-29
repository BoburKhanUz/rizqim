// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket2 _$Ticket2FromJson(Map<String, dynamic> json) => Ticket2(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket2ToJson(Ticket2 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
