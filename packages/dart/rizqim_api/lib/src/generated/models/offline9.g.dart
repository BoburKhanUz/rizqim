// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline9 _$Offline9FromJson(Map<String, dynamic> json) => Offline9(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags8.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline9ToJson(Offline9 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
