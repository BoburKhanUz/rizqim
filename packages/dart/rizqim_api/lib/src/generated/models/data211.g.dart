// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data211.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data211 _$Data211FromJson(Map<String, dynamic> json) => Data211(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  type: Type10.fromJson(json['type'] as String),
  amount: json['amount'] as num,
  discountAmount: json['discount_amount'] as num?,
  description: json['description'] as String,
);

Map<String, dynamic> _$Data211ToJson(Data211 instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'type': instance.type,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'description': instance.description,
};
