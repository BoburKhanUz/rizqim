// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'discounts4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Discounts4 _$Discounts4FromJson(Map<String, dynamic> json) => Discounts4(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  offline: json['offline'] == null
      ? null
      : Offline30.fromJson(json['offline'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Discounts4ToJson(Discounts4 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'offline': instance.offline,
    };
