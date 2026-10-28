// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tariff_plan.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

TariffPlan _$TariffPlanFromJson(Map<String, dynamic> json) => TariffPlan(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  code: json['code'] as String,
  isActive: json['is_active'] as bool,
  commissionPercent: json['commission_percent'] as String?,
  monthlyBaseFee: json['monthly_base_fee'] as String?,
  monthlyFeePerTerminal: json['monthly_fee_per_terminal'] as String?,
  includedTerminals: (json['included_terminals'] as num).toInt(),
  trialDays: (json['trial_days'] as num).toInt(),
  description: json['description'] as String?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  version: (json['version'] as num).toInt(),
  tier: json['tier'] as String?,
  sortOrder: (json['sort_order'] as num).toInt(),
  yearlyFee: json['yearly_fee'] as String?,
  includedPos: (json['included_pos'] as num).toInt(),
  includedKds: (json['included_kds'] as num).toInt(),
  extraPosFee: json['extra_pos_fee'] as String?,
  extraKdsFee: json['extra_kds_fee'] as String?,
  features: (json['features'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  subscriptions: (json['subscriptions'] as List<dynamic>?)
      ?.map((e) => StoreSubscription.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$TariffPlanToJson(TariffPlan instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'code': instance.code,
      'is_active': instance.isActive,
      'commission_percent': instance.commissionPercent,
      'monthly_base_fee': instance.monthlyBaseFee,
      'monthly_fee_per_terminal': instance.monthlyFeePerTerminal,
      'included_terminals': instance.includedTerminals,
      'trial_days': instance.trialDays,
      'description': instance.description,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'version': instance.version,
      'tier': instance.tier,
      'sort_order': instance.sortOrder,
      'yearly_fee': instance.yearlyFee,
      'included_pos': instance.includedPos,
      'included_kds': instance.includedKds,
      'extra_pos_fee': instance.extraPosFee,
      'extra_kds_fee': instance.extraKdsFee,
      'features': instance.features,
      'subscriptions': instance.subscriptions,
    };
