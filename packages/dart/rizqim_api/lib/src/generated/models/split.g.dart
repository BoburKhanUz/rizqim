// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'split.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Split _$SplitFromJson(Map<String, dynamic> json) => Split(
  name: json['name'] as String,
  mxikCode: json['mxik_code'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$SplitToJson(Split instance) => <String, dynamic>{
  'name': instance.name,
  'mxik_code': instance.mxikCode,
  'quantity': instance.quantity,
  'amount': instance.amount,
};
