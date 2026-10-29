// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bank.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bank _$BankFromJson(Map<String, dynamic> json) =>
    Bank(rrn: json['rrn'] as String?, amount: json['amount'] as String);

Map<String, dynamic> _$BankToJson(Bank instance) => <String, dynamic>{
  'rrn': instance.rrn,
  'amount': instance.amount,
};
