// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'transactions2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Transactions2 _$Transactions2FromJson(Map<String, dynamic> json) =>
    Transactions2(
      rrn: json['rrn'] as String?,
      amount: json['amount'] as String,
      matched: json['matched'] as bool?,
    );

Map<String, dynamic> _$Transactions2ToJson(Transactions2 instance) =>
    <String, dynamic>{
      'rrn': instance.rrn,
      'amount': instance.amount,
      'matched': instance.matched,
    };
