// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data280.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data280 _$Data280FromJson(Map<String, dynamic> json) => Data280(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data280ToJson(Data280 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
