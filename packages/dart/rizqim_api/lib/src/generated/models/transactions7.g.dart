// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'transactions7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Transactions7 _$Transactions7FromJson(Map<String, dynamic> json) =>
    Transactions7(
      rrn: json['rrn'] as String?,
      amount: json['amount'] as String,
      matched: json['matched'] as bool,
    );

Map<String, dynamic> _$Transactions7ToJson(Transactions7 instance) =>
    <String, dynamic>{
      'rrn': instance.rrn,
      'amount': instance.amount,
      'matched': instance.matched,
    };
