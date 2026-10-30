// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batch_writeoffs4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BatchWriteoffs4 _$BatchWriteoffs4FromJson(Map<String, dynamic> json) =>
    BatchWriteoffs4(
      count: (json['count'] as num).toInt(),
      items: (json['items'] as List<dynamic>)
          .map((e) => Items54.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$BatchWriteoffs4ToJson(BatchWriteoffs4 instance) =>
    <String, dynamic>{'count': instance.count, 'items': instance.items};
