// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'overdue.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Overdue _$OverdueFromJson(Map<String, dynamic> json) => Overdue(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$OverdueToJson(Overdue instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
};
