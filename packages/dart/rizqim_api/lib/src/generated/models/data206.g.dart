// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data206.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data206 _$Data206FromJson(Map<String, dynamic> json) => Data206(
  session: json['session'] == null
      ? null
      : Session3.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data206ToJson(Data206 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
