// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'plan2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Plan2 _$Plan2FromJson(Map<String, dynamic> json) => Plan2(
  code: json['code'] as String,
  name: json['name'] as String,
  description: json['description'] as String?,
  summary: json['summary'] as String,
  commissionPercent: json['commission_percent'] as num?,
  monthlyBaseFee: json['monthly_base_fee'] as num,
  monthlyFeePerTerminal: json['monthly_fee_per_terminal'] as num,
  includedTerminals: (json['included_terminals'] as num).toInt(),
  trialDays: (json['trial_days'] as num).toInt(),
);

Map<String, dynamic> _$Plan2ToJson(Plan2 instance) => <String, dynamic>{
  'code': instance.code,
  'name': instance.name,
  'description': instance.description,
  'summary': instance.summary,
  'commission_percent': instance.commissionPercent,
  'monthly_base_fee': instance.monthlyBaseFee,
  'monthly_fee_per_terminal': instance.monthlyFeePerTerminal,
  'included_terminals': instance.includedTerminals,
  'trial_days': instance.trialDays,
};
