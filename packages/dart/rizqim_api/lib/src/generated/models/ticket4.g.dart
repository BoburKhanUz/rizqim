// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ticket4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ticket4 _$Ticket4FromJson(Map<String, dynamic> json) => Ticket4(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  orderLabel: json['order_label'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$Ticket4ToJson(Ticket4 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'order_label': instance.orderLabel,
  'message': instance.message,
};
