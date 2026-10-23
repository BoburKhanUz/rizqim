// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline7 _$Offline7FromJson(Map<String, dynamic> json) => Offline7(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags6.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline7ToJson(Offline7 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
