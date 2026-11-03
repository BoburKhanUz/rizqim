// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'single.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Single _$SingleFromJson(Map<String, dynamic> json) => Single(
  name: json['name'] as String,
  mxikCode: json['mxik_code'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$SingleToJson(Single instance) => <String, dynamic>{
  'name': instance.name,
  'mxik_code': instance.mxikCode,
  'quantity': instance.quantity,
  'amount': instance.amount,
};
