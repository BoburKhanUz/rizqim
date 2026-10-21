// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data81.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data81 _$Data81FromJson(Map<String, dynamic> json) => Data81(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type7.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data81ToJson(Data81 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
