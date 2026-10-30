// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_method11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByMethod11 _$ByMethod11FromJson(Map<String, dynamic> json) => ByMethod11(
  method: json['method'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByMethod11ToJson(ByMethod11 instance) =>
    <String, dynamic>{
      'method': instance.method,
      'count': instance.count,
      'amount': instance.amount,
    };
