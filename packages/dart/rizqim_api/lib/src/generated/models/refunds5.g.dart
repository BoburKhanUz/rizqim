// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunds5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunds5 _$Refunds5FromJson(Map<String, dynamic> json) => Refunds5(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Refunds5ToJson(Refunds5 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
};
