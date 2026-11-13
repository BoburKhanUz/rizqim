// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data221.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data221 _$Data221FromJson(Map<String, dynamic> json) => Data221(
  session: json['session'] == null
      ? null
      : Session2.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data221ToJson(Data221 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
