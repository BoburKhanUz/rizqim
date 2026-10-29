// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'transactions.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Transactions _$TransactionsFromJson(Map<String, dynamic> json) => Transactions(
  rrn: json['rrn'] as String?,
  amount: json['amount'] as String,
  matched: json['matched'] as bool,
);

Map<String, dynamic> _$TransactionsToJson(Transactions instance) =>
    <String, dynamic>{
      'rrn': instance.rrn,
      'amount': instance.amount,
      'matched': instance.matched,
    };
