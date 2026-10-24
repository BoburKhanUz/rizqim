// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data182.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data182 _$Data182FromJson(Map<String, dynamic> json) => Data182(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type10.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data182ToJson(Data182 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
