// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'module_rows2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ModuleRows2 _$ModuleRows2FromJson(Map<String, dynamic> json) => ModuleRows2(
  key: json['key'] as String,
  label: json['label'] as String,
  description: json['description'] as String,
  defaultValue: json['default'] as bool,
  override: json['override'] as bool?,
  configured: json['configured'] as bool,
  planAllows: json['plan_allows'] as bool,
  requiredPlan: json['required_plan'] as String?,
  requiredPlanName: json['required_plan_name'] as String?,
  locked: json['locked'] as String?,
  enabled: json['enabled'] as bool,
);

Map<String, dynamic> _$ModuleRows2ToJson(ModuleRows2 instance) =>
    <String, dynamic>{
      'key': instance.key,
      'label': instance.label,
      'description': instance.description,
      'default': instance.defaultValue,
      'override': instance.override,
      'configured': instance.configured,
      'plan_allows': instance.planAllows,
      'required_plan': instance.requiredPlan,
      'required_plan_name': instance.requiredPlanName,
      'locked': instance.locked,
      'enabled': instance.enabled,
    };
