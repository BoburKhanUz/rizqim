// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data279.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data279 _$Data279FromJson(Map<String, dynamic> json) => Data279(
  session: json['session'] == null
      ? null
      : Session2.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data279ToJson(Data279 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
