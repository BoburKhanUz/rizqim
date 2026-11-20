// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'discounts2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Discounts2 _$Discounts2FromJson(Map<String, dynamic> json) => Discounts2(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  offline: json['offline'] == null
      ? null
      : Offline28.fromJson(json['offline'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Discounts2ToJson(Discounts2 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'offline': instance.offline,
    };
