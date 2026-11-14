// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_status.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStatus _$ByStatusFromJson(Map<String, dynamic> json) => ByStatus(
  status: json['status'] as String,
  count: (json['count'] as num).toInt(),
);

Map<String, dynamic> _$ByStatusToJson(ByStatus instance) => <String, dynamic>{
  'status': instance.status,
  'count': instance.count,
};
