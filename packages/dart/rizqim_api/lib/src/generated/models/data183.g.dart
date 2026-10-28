// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data183.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data183 _$Data183FromJson(Map<String, dynamic> json) => Data183(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type10.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data183ToJson(Data183 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
