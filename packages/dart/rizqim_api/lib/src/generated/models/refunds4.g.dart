// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunds4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunds4 _$Refunds4FromJson(Map<String, dynamic> json) => Refunds4(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Refunds4ToJson(Refunds4 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
};
