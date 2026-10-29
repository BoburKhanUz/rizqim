// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket3 _$Ticket3FromJson(Map<String, dynamic> json) => Ticket3(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket3ToJson(Ticket3 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
