// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data79.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data79 _$Data79FromJson(Map<String, dynamic> json) => Data79(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type7.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data79ToJson(Data79 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
