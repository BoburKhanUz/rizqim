// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal _$FiscalFromJson(Map<String, dynamic> json) => Fiscal(
  enabled: json['enabled'] as bool,
  activeDriver: json['active_driver'] as String,
  smartKassaDriver: json['smart_kassa_driver'] as String,
);

Map<String, dynamic> _$FiscalToJson(Fiscal instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'active_driver': instance.activeDriver,
  'smart_kassa_driver': instance.smartKassaDriver,
};
