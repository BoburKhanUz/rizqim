// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'branches3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Branches3 _$Branches3FromJson(Map<String, dynamic> json) => Branches3(
  branch: Branch8.fromJson(json['branch'] as Map<String, dynamic>),
  moduleEnabled: json['module_enabled'] as bool,
  setup: Setup.fromJson(json['setup'] as Map<String, dynamic>),
  devices: (json['devices'] as List<dynamic>)
      .map((e) => Devices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Branches3ToJson(Branches3 instance) => <String, dynamic>{
  'branch': instance.branch,
  'module_enabled': instance.moduleEnabled,
  'setup': instance.setup,
  'devices': instance.devices,
};
