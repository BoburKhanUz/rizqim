// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'business_day2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BusinessDay2 _$BusinessDay2FromJson(Map<String, dynamic> json) => BusinessDay2(
  businessDate: json['business_date'] as String,
  startsAt: json['starts_at'] as String,
  startedAt: json['started_at'] as String,
  nextStartsAt: json['next_starts_at'] as String,
  roundTheClock: json['round_the_clock'] as bool,
);

Map<String, dynamic> _$BusinessDay2ToJson(BusinessDay2 instance) =>
    <String, dynamic>{
      'business_date': instance.businessDate,
      'starts_at': instance.startsAt,
      'started_at': instance.startedAt,
      'next_starts_at': instance.nextStartsAt,
      'round_the_clock': instance.roundTheClock,
    };
