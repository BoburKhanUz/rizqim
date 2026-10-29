// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batch_writeoffs3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BatchWriteoffs3 _$BatchWriteoffs3FromJson(Map<String, dynamic> json) =>
    BatchWriteoffs3(
      count: (json['count'] as num).toInt(),
      items: (json['items'] as List<dynamic>)
          .map((e) => Items44.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$BatchWriteoffs3ToJson(BatchWriteoffs3 instance) =>
    <String, dynamic>{'count': instance.count, 'items': instance.items};
