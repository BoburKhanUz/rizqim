// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_method2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByMethod2 _$ByMethod2FromJson(Map<String, dynamic> json) => ByMethod2(
  method: json['method'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByMethod2ToJson(ByMethod2 instance) => <String, dynamic>{
  'method': instance.method,
  'count': instance.count,
  'amount': instance.amount,
};
