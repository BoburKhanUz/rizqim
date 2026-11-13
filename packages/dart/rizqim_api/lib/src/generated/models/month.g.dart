// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'month.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Month _$MonthFromJson(Map<String, dynamic> json) => Month(
  amount: json['amount'] as String,
  monthlyLimit: json['monthly_limit'] as String?,
  available: json['available'] as String?,
);

Map<String, dynamic> _$MonthToJson(Month instance) => <String, dynamic>{
  'amount': instance.amount,
  'monthly_limit': instance.monthlyLimit,
  'available': instance.available,
};
