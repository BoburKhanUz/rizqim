// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline22.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline22 _$Offline22FromJson(Map<String, dynamic> json) => Offline22(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags21.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline22ToJson(Offline22 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
