// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_method10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByMethod10 _$ByMethod10FromJson(Map<String, dynamic> json) => ByMethod10(
  method: json['method'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByMethod10ToJson(ByMethod10 instance) =>
    <String, dynamic>{
      'method': instance.method,
      'count': instance.count,
      'amount': instance.amount,
    };
