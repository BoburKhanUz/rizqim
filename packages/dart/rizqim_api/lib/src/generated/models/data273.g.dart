// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data273.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data273 _$Data273FromJson(Map<String, dynamic> json) => Data273(
  session: json['session'] == null
      ? null
      : Session3.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data273ToJson(Data273 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
