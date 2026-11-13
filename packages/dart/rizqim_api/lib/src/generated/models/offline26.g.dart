// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline26.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline26 _$Offline26FromJson(Map<String, dynamic> json) => Offline26(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags25.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline26ToJson(Offline26 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
