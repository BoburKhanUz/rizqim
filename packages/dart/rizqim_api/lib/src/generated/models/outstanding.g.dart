// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'outstanding.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Outstanding _$OutstandingFromJson(Map<String, dynamic> json) => Outstanding(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$OutstandingToJson(Outstanding instance) =>
    <String, dynamic>{'count': instance.count, 'amount': instance.amount};
