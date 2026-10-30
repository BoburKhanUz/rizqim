// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline21.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline21 _$Offline21FromJson(Map<String, dynamic> json) => Offline21(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags20.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline21ToJson(Offline21 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
