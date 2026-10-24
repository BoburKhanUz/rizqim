// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline3 _$Offline3FromJson(Map<String, dynamic> json) => Offline3(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline3ToJson(Offline3 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
