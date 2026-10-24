// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline13 _$Offline13FromJson(Map<String, dynamic> json) => Offline13(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline13ToJson(Offline13 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
