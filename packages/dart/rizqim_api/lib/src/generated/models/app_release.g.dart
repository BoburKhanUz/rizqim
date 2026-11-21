// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_release.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

AppRelease _$AppReleaseFromJson(Map<String, dynamic> json) => AppRelease(
  current: json['current'] as String?,
  latest: json['latest'] as String,
  minimum: json['minimum'] as String?,
  state: json['state'] as String,
  url: json['url'] as String,
  notes: json['notes'] as String?,
  minimumFrom: json['minimum_from'] as String?,
);

Map<String, dynamic> _$AppReleaseToJson(AppRelease instance) =>
    <String, dynamic>{
      'current': instance.current,
      'latest': instance.latest,
      'minimum': instance.minimum,
      'state': instance.state,
      'url': instance.url,
      'notes': instance.notes,
      'minimum_from': instance.minimumFrom,
    };
