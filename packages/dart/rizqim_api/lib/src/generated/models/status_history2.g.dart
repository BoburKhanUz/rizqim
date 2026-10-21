// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'status_history2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StatusHistory2 _$StatusHistory2FromJson(Map<String, dynamic> json) =>
    StatusHistory2(
      fromStatus: json['from_status'] as String?,
      status: json['status'] as String,
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$StatusHistory2ToJson(StatusHistory2 instance) =>
    <String, dynamic>{
      'from_status': instance.fromStatus,
      'status': instance.status,
      'created_at': instance.createdAt,
    };
