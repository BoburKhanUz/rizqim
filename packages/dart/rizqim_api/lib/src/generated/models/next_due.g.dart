// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'next_due.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

NextDue _$NextDueFromJson(Map<String, dynamic> json) => NextDue(
  date: json['date'] as String,
  amount: json['amount'] as String,
  kind: json['kind'] as String,
);

Map<String, dynamic> _$NextDueToJson(NextDue instance) => <String, dynamic>{
  'date': instance.date,
  'amount': instance.amount,
  'kind': instance.kind,
};
