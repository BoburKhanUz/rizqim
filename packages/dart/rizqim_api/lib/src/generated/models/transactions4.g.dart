// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'transactions4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Transactions4 _$Transactions4FromJson(Map<String, dynamic> json) =>
    Transactions4(
      rrn: json['rrn'] as String?,
      amount: json['amount'] as String,
      matched: json['matched'] as bool?,
    );

Map<String, dynamic> _$Transactions4ToJson(Transactions4 instance) =>
    <String, dynamic>{
      'rrn': instance.rrn,
      'amount': instance.amount,
      'matched': instance.matched,
    };
