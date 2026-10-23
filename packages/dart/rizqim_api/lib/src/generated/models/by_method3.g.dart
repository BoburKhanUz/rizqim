// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_method3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByMethod3 _$ByMethod3FromJson(Map<String, dynamic> json) => ByMethod3(
  method: json['method'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByMethod3ToJson(ByMethod3 instance) => <String, dynamic>{
  'method': instance.method,
  'count': instance.count,
  'amount': instance.amount,
};
