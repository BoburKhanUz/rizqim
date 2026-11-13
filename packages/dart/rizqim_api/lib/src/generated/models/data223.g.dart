// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data223.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data223 _$Data223FromJson(Map<String, dynamic> json) => Data223(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data223ToJson(Data223 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
