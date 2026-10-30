// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'batch_writeoffs5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BatchWriteoffs5 _$BatchWriteoffs5FromJson(Map<String, dynamic> json) =>
    BatchWriteoffs5(
      count: (json['count'] as num).toInt(),
      items: (json['items'] as List<dynamic>)
          .map((e) => Items51.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$BatchWriteoffs5ToJson(BatchWriteoffs5 instance) =>
    <String, dynamic>{'count': instance.count, 'items': instance.items};
