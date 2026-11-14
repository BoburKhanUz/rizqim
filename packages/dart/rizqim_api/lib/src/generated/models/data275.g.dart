// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data275.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data275 _$Data275FromJson(Map<String, dynamic> json) => Data275(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data275ToJson(Data275 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
