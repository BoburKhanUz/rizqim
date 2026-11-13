// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunds9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunds9 _$Refunds9FromJson(Map<String, dynamic> json) => Refunds9(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Refunds9ToJson(Refunds9 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
};
