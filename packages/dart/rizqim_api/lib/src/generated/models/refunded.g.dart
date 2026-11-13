// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded _$RefundedFromJson(Map<String, dynamic> json) => Refunded(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$RefundedToJson(Refunded instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
};
