// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settings2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settings2 _$Settings2FromJson(Map<String, dynamic> json) => Settings2(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  providers: (json['providers'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  defaultProvider: json['default_provider'] as String?,
  expiryMinutes: (json['expiry_minutes'] as num).toInt(),
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Settings2ToJson(Settings2 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'providers': instance.providers,
  'default_provider': instance.defaultProvider,
  'expiry_minutes': instance.expiryMinutes,
  'updated_at': instance.updatedAt,
};
