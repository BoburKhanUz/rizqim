// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline14 _$Offline14FromJson(Map<String, dynamic> json) => Offline14(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags13.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline14ToJson(Offline14 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
