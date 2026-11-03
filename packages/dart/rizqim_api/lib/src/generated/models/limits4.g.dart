// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'limits4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Limits4 _$Limits4FromJson(Map<String, dynamic> json) => Limits4(
  batchSize: (json['batch_size'] as num).toInt(),
  warnAfterMinutes: (json['warn_after_minutes'] as num).toInt(),
  alertAfterMinutes: (json['alert_after_minutes'] as num).toInt(),
  maxQueuedOrders: (json['max_queued_orders'] as num).toInt(),
  menuMaxAgeHours: (json['menu_max_age_hours'] as num).toInt(),
  contactMaxAgeHours: (json['contact_max_age_hours'] as num).toInt(),
  fiscalWarnHours: (json['fiscal_warn_hours'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  fiscalLimitHours: (json['fiscal_limit_hours'] as num).toInt(),
  clockSkewWarnSeconds: (json['clock_skew_warn_seconds'] as num).toInt(),
);

Map<String, dynamic> _$Limits4ToJson(Limits4 instance) => <String, dynamic>{
  'batch_size': instance.batchSize,
  'warn_after_minutes': instance.warnAfterMinutes,
  'alert_after_minutes': instance.alertAfterMinutes,
  'max_queued_orders': instance.maxQueuedOrders,
  'menu_max_age_hours': instance.menuMaxAgeHours,
  'contact_max_age_hours': instance.contactMaxAgeHours,
  'fiscal_warn_hours': instance.fiscalWarnHours,
  'fiscal_limit_hours': instance.fiscalLimitHours,
  'clock_skew_warn_seconds': instance.clockSkewWarnSeconds,
};
