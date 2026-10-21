// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'status_history.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StatusHistory _$StatusHistoryFromJson(Map<String, dynamic> json) =>
    StatusHistory(
      fromStatus: json['from_status'] as String?,
      status: json['status'] as String,
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$StatusHistoryToJson(StatusHistory instance) =>
    <String, dynamic>{
      'from_status': instance.fromStatus,
      'status': instance.status,
      'created_at': instance.createdAt,
    };
