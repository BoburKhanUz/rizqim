// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline10 _$Offline10FromJson(Map<String, dynamic> json) => Offline10(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags9.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline10ToJson(Offline10 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
