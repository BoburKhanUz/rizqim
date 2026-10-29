// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'transactions3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Transactions3 _$Transactions3FromJson(Map<String, dynamic> json) =>
    Transactions3(
      amount: json['amount'] as String,
      rrn: json['rrn'] as String?,
    );

Map<String, dynamic> _$Transactions3ToJson(Transactions3 instance) =>
    <String, dynamic>{'rrn': instance.rrn, 'amount': instance.amount};
