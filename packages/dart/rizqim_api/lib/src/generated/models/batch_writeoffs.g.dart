// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batch_writeoffs.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BatchWriteoffs _$BatchWriteoffsFromJson(Map<String, dynamic> json) =>
    BatchWriteoffs(
      count: (json['count'] as num).toInt(),
      items: (json['items'] as List<dynamic>)
          .map((e) => Items51.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$BatchWriteoffsToJson(BatchWriteoffs instance) =>
    <String, dynamic>{'count': instance.count, 'items': instance.items};
