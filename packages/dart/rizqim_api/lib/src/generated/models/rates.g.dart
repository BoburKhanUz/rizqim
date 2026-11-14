// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rates.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rates _$RatesFromJson(Map<String, dynamic> json) => Rates(
  channel: json['channel'] as String,
  percent: json['percent'] as String,
  note: json['note'] as String?,
);

Map<String, dynamic> _$RatesToJson(Rates instance) => <String, dynamic>{
  'channel': instance.channel,
  'percent': instance.percent,
  'note': instance.note,
};
