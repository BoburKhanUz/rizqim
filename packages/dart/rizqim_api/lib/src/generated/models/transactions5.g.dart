// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'transactions5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Transactions5 _$Transactions5FromJson(Map<String, dynamic> json) =>
    Transactions5(
      rrn: json['rrn'] as String?,
      amount: json['amount'] as String,
      matched: json['matched'] as bool,
    );

Map<String, dynamic> _$Transactions5ToJson(Transactions5 instance) =>
    <String, dynamic>{
      'rrn': instance.rrn,
      'amount': instance.amount,
      'matched': instance.matched,
    };
