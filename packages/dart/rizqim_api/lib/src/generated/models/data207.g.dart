// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data207.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data207 _$Data207FromJson(Map<String, dynamic> json) => Data207(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data207ToJson(Data207 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
