// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket9 _$Ticket9FromJson(Map<String, dynamic> json) => Ticket9(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket9ToJson(Ticket9 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
