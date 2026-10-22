// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'discounts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Discounts _$DiscountsFromJson(Map<String, dynamic> json) => Discounts(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$DiscountsToJson(Discounts instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
};
