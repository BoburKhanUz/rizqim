// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stations2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Stations2 _$Stations2FromJson(Map<String, dynamic> json) => Stations2(
  name: json['name'] as String,
  kind: json['kind'] as String,
  kindLabel: json['kind_label'] as String,
  exists: json['exists'] as bool,
);

Map<String, dynamic> _$Stations2ToJson(Stations2 instance) => <String, dynamic>{
  'name': instance.name,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'exists': instance.exists,
};
