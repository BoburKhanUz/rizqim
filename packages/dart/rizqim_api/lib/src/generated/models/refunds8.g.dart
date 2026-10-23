// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunds8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunds8 _$Refunds8FromJson(Map<String, dynamic> json) => Refunds8(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Refunds8ToJson(Refunds8 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
};
