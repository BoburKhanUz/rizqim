// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data211.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data211 _$Data211FromJson(Map<String, dynamic> json) => Data211(
  session: json['session'] == null
      ? null
      : Session3.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data211ToJson(Data211 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
