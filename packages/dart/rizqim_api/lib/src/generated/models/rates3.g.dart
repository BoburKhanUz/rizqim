// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rates3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rates3 _$Rates3FromJson(Map<String, dynamic> json) => Rates3(
  channel: Channel3.fromJson(json['channel'] as String),
  percent: json['percent'] as num,
  note: json['note'] as String?,
);

Map<String, dynamic> _$Rates3ToJson(Rates3 instance) => <String, dynamic>{
  'channel': instance.channel,
  'percent': instance.percent,
  'note': instance.note,
};
