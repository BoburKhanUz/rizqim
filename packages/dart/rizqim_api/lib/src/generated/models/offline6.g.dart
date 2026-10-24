// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline6 _$Offline6FromJson(Map<String, dynamic> json) => Offline6(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline6ToJson(Offline6 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
