// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline8 _$Offline8FromJson(Map<String, dynamic> json) => Offline8(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags7.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline8ToJson(Offline8 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
