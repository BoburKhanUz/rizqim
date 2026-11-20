// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data277.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data277 _$Data277FromJson(Map<String, dynamic> json) => Data277(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data277ToJson(Data277 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
