// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'discounts5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Discounts5 _$Discounts5FromJson(Map<String, dynamic> json) => Discounts5(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  offline: json['offline'] == null
      ? null
      : Offline31.fromJson(json['offline'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Discounts5ToJson(Discounts5 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'offline': instance.offline,
    };
