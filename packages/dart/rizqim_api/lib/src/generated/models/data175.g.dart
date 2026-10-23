// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data175.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data175 _$Data175FromJson(Map<String, dynamic> json) => Data175(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type9.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data175ToJson(Data175 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
