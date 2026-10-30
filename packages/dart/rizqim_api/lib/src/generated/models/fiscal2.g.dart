// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal2 _$Fiscal2FromJson(Map<String, dynamic> json) => Fiscal2(
  enabled: json['enabled'] as bool,
  activeDriver: json['active_driver'] as String,
  smartKassaDriver: json['smart_kassa_driver'] as String,
);

Map<String, dynamic> _$Fiscal2ToJson(Fiscal2 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'active_driver': instance.activeDriver,
  'smart_kassa_driver': instance.smartKassaDriver,
};
