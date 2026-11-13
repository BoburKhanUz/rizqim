// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'usage.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Usage _$UsageFromJson(Map<String, dynamic> json) => Usage(
  businessDate: json['business_date'] as String,
  member: json['member'] == null
      ? null
      : Member2.fromJson(json['member'] as Map<String, dynamic>),
  month: Month.fromJson(json['month'] as Map<String, dynamic>),
  creditAvailable: json['credit_available'] as String?,
);

Map<String, dynamic> _$UsageToJson(Usage instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'member': instance.member,
  'month': instance.month,
  'credit_available': instance.creditAvailable,
};
