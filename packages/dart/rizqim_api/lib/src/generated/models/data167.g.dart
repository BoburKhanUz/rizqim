// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data167.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data167 _$Data167FromJson(Map<String, dynamic> json) => Data167(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type9.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data167ToJson(Data167 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
