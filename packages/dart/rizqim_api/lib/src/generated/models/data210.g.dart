// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data210.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data210 _$Data210FromJson(Map<String, dynamic> json) => Data210(
  session: json['session'] == null
      ? null
      : Session2.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data210ToJson(Data210 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
