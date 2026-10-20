// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data55.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data55 _$Data55FromJson(Map<String, dynamic> json) => Data55(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type6.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data55ToJson(Data55 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
