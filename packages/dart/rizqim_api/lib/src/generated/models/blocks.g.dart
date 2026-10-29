// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'blocks.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Blocks _$BlocksFromJson(Map<String, dynamic> json) => Blocks(
  start: (json['start'] as num).toInt(),
  end: (json['end'] as num).toInt(),
  next: (json['next'] as num).toInt(),
);

Map<String, dynamic> _$BlocksToJson(Blocks instance) => <String, dynamic>{
  'start': instance.start,
  'end': instance.end,
  'next': instance.next,
};
