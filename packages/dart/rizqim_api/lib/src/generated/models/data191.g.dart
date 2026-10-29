// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data191.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data191 _$Data191FromJson(Map<String, dynamic> json) => Data191(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data191ToJson(Data191 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
