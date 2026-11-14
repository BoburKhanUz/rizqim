// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket15 _$Ticket15FromJson(Map<String, dynamic> json) => Ticket15(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket15ToJson(Ticket15 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
