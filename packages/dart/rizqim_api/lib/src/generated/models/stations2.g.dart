// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stations2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Stations2 _$Stations2FromJson(Map<String, dynamic> json) => Stations2(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String,
  kindLabel: json['kind_label'] as String,
  isActive: json['is_active'] as bool,
  targetSeconds: (json['target_seconds'] as num?)?.toInt(),
  defaultTargetSeconds: (json['default_target_seconds'] as num?)?.toInt(),
);

Map<String, dynamic> _$Stations2ToJson(Stations2 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'is_active': instance.isActive,
  'target_seconds': instance.targetSeconds,
  'default_target_seconds': instance.defaultTargetSeconds,
};
