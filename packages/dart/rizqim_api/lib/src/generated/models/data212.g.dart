// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data212.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data212 _$Data212FromJson(Map<String, dynamic> json) => Data212(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data212ToJson(Data212 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
