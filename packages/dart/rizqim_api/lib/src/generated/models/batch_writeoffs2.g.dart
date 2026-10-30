// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batch_writeoffs2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BatchWriteoffs2 _$BatchWriteoffs2FromJson(Map<String, dynamic> json) =>
    BatchWriteoffs2(
      count: (json['count'] as num).toInt(),
      items: (json['items'] as List<dynamic>)
          .map((e) => Items52.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$BatchWriteoffs2ToJson(BatchWriteoffs2 instance) =>
    <String, dynamic>{'count': instance.count, 'items': instance.items};
