// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'file.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

File _$FileFromJson(Map<String, dynamic> json) => File(
  version: (json['version'] as num).toInt(),
  device: json['device'] as String,
  kid: (json['kid'] as num).toInt(),
  createdAt: json['created_at'] as String?,
  count: (json['count'] as num).toInt(),
  firstSeq: (json['first_seq'] as num?)?.toInt(),
  lastSeq: (json['last_seq'] as num?)?.toInt(),
);

Map<String, dynamic> _$FileToJson(File instance) => <String, dynamic>{
  'version': instance.version,
  'device': instance.device,
  'kid': instance.kid,
  'created_at': instance.createdAt,
  'count': instance.count,
  'first_seq': instance.firstSeq,
  'last_seq': instance.lastSeq,
};
