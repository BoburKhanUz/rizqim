// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunds6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunds6 _$Refunds6FromJson(Map<String, dynamic> json) => Refunds6(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Refunds6ToJson(Refunds6 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
};
