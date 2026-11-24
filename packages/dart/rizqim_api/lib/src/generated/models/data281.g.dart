// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data281.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data281 _$Data281FromJson(Map<String, dynamic> json) => Data281(
  session: json['session'] == null
      ? null
      : Session3.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data281ToJson(Data281 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
