// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline18.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline18 _$Offline18FromJson(Map<String, dynamic> json) => Offline18(
  localNumber: json['local_number'] as String?,
  createdAt: json['created_at'] as String?,
  syncedAt: json['synced_at'] as String?,
  flags: (json['flags'] as List<dynamic>)
      .map((e) => Flags17.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Offline18ToJson(Offline18 instance) => <String, dynamic>{
  'local_number': instance.localNumber,
  'created_at': instance.createdAt,
  'synced_at': instance.syncedAt,
  'flags': instance.flags,
};
