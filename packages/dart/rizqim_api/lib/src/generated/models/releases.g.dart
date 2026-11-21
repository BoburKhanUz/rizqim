// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'releases.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Releases _$ReleasesFromJson(Map<String, dynamic> json) => Releases(
  app: json['app'] as String,
  platform: json['platform'] as String,
  version: json['version'] as String,
  build: (json['build'] as num).toInt(),
  fileUrl: json['file_url'] as String,
  sha256: json['sha256'] as String,
  sizeBytes: (json['size_bytes'] as num).toInt(),
  notes: json['notes'] as String?,
  releasedAt: json['released_at'] as String,
  isMinimum: json['is_minimum'] as bool,
  minimumFrom: json['minimum_from'] as String?,
  isLatest: json['is_latest'] as bool,
);

Map<String, dynamic> _$ReleasesToJson(Releases instance) => <String, dynamic>{
  'app': instance.app,
  'platform': instance.platform,
  'version': instance.version,
  'build': instance.build,
  'file_url': instance.fileUrl,
  'sha256': instance.sha256,
  'size_bytes': instance.sizeBytes,
  'notes': instance.notes,
  'released_at': instance.releasedAt,
  'is_minimum': instance.isMinimum,
  'minimum_from': instance.minimumFrom,
  'is_latest': instance.isLatest,
};
