// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunds7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunds7 _$Refunds7FromJson(Map<String, dynamic> json) => Refunds7(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Refunds7ToJson(Refunds7 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
};
