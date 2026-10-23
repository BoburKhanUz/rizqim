// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_method.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByMethod _$ByMethodFromJson(Map<String, dynamic> json) => ByMethod(
  method: json['method'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByMethodToJson(ByMethod instance) => <String, dynamic>{
  'method': instance.method,
  'count': instance.count,
  'amount': instance.amount,
};
